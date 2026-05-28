<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\ServiceAccess;
use App\Models\User;
use App\Services\Policy\PermissionCatalogService;
use App\Services\Policy\RolePolicyService;
use App\Services\Registration\RegistrationPolicyService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserProjectionService
{
    public function __construct(
        private readonly RegistrationPolicyService $registrationPolicyService,
        private readonly PermissionCatalogService $permissionCatalogService,
        private readonly RolePolicyService $rolePolicyService,
    ) {}

    public function syncFromIdentity(PlatformIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $coreRoles = $this->ensureCoreRoles();
            $user = User::query()->firstWhere('keycloak_subject', $identity->subject);
            $policy = $this->registrationPolicyService->current();
            $matchedPreProvisionedUser = false;

            if ($user === null) {
                $user = User::query()
                    ->whereNull('keycloak_subject')
                    ->where('email', $identity->email)
                    ->first();

                $matchedPreProvisionedUser = $user !== null;
            }

            if ($user === null) {
                $user = User::query()->create([
                    'keycloak_subject' => $identity->subject,
                    'email' => $identity->email,
                    'name' => $identity->name ?? $identity->preferredUsername ?? 'Unknown User',
                    'display_name' => $identity->name,
                    'status' => $policy->default_new_user_status,
                    'last_login_at' => now(),
                ]);

                $this->syncBootstrapRoles($user, $identity, $coreRoles, $matchedPreProvisionedUser);
                $this->syncServiceAccesses($user, $identity);

                AuditLog::query()->create([
                    'actor_subject' => $identity->subject,
                    'action' => 'user_projection_created',
                    'target_type' => 'user',
                    'target_id' => (string) $user->id,
                    'payload' => [
                        'email' => $user->email,
                        'status' => $user->status,
                    ],
                ]);

                return $user->refresh();
            }

            $dirty = false;

            if ($user->keycloak_subject !== $identity->subject) {
                $user->keycloak_subject = $identity->subject;
                $dirty = true;
            }

            foreach ([
                'email' => $identity->email,
                'name' => $identity->name ?? $identity->preferredUsername ?? $user->name,
                'display_name' => $identity->name,
            ] as $field => $value) {
                if ($user->{$field} !== $value) {
                    $user->{$field} = $value;
                    $dirty = true;
                }
            }

            $user->last_login_at = now();
            $dirty = true;
            $user->save();

            $this->syncBootstrapRoles($user, $identity, $coreRoles, $matchedPreProvisionedUser);
            $this->syncServiceAccesses($user, $identity);

            if ($dirty) {
                AuditLog::query()->create([
                    'actor_subject' => $identity->subject,
                    'action' => 'user_projection_updated',
                    'target_type' => 'user',
                    'target_id' => (string) $user->id,
                    'payload' => [
                        'email' => $user->email,
                        'status' => $user->status,
                    ],
                ]);
            }

            return $user->refresh();
        });
    }

    public function effectiveRoleCodes(User $user): Collection
    {
        $user->loadMissing('roles');

        return $user->roles
            ->pluck('code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->values();
    }

    public function effectivePermissionCodes(User $user): Collection
    {
        $user->loadMissing('roles.permissions');

        return $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('code'))
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();
    }

    private function ensureCoreRoles(): array
    {
        $this->permissionCatalogService->syncCatalog();

        $platformOperator = Role::query()->firstOrCreate(
            ['code' => 'platform_operator'],
            [
                'name' => 'Platform Operator',
                'description' => 'Bootstrap administrator role for platform operations.',
                'is_system' => true,
                'is_deletable' => false,
            ],
        );

        $this->rolePolicyService->assignPermissions($platformOperator, [
            'dashboard.view',
            'roles.manage',
            'users.manage',
            'settings.manage',
        ]);

        $superAdmin = Role::query()->firstOrCreate(
            ['code' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to all platform permissions.',
                'is_system' => true,
                'is_deletable' => false,
            ],
        );

        $this->rolePolicyService->assignPermissions($superAdmin, []);

        return [
            'platform_operator' => $platformOperator,
            'super_admin' => $superAdmin,
        ];
    }

    private function syncBootstrapRoles(User $user, PlatformIdentity $identity, array $coreRoles, bool $matchedPreProvisionedUser): void
    {
        $roleIds = [];

        if ($identity->hasRealmRole('platform_operator') && isset($coreRoles['platform_operator'])) {
            $roleIds[] = $coreRoles['platform_operator']->id;
        }

        if ($identity->hasRealmRole('super_admin') && isset($coreRoles['super_admin'])) {
            $roleIds[] = $coreRoles['super_admin']->id;
        }

        if (
            $roleIds === []
            && $matchedPreProvisionedUser
            && $user->roles()->count() === 0
            && DB::table('user_roles')->count() === 0
            && isset($coreRoles['platform_operator'])
        ) {
            $roleIds[] = $coreRoles['platform_operator']->id;
        }

        if ($roleIds !== []) {
            $user->roles()->syncWithoutDetaching($roleIds);
            $user->load('roles.permissions');
        }
    }

    private function syncServiceAccesses(User $user, PlatformIdentity $identity): void
    {
        $user->loadMissing('roles.permissions', 'serviceAccesses');

        foreach (['platform', 'supply', 'calculation'] as $serviceCode) {
            ServiceAccess::query()->firstOrCreate([
                'user_id' => $user->id,
                'service_code' => $serviceCode,
            ], [
                'access_status' => 'pending',
            ]);
        }

        $allowedServices = $this->resolveAllowedServices($user, $identity);

        if ($allowedServices->isEmpty()) {
            return;
        }

        ServiceAccess::query()
            ->where('user_id', $user->id)
            ->whereIn('service_code', $allowedServices->all())
            ->where('access_status', 'pending')
            ->update([
                'access_status' => 'allowed',
                'updated_at' => now(),
            ]);

        if ($user->status === 'pending_access') {
            $user->forceFill([
                'status' => 'active',
            ])->save();
        }
    }

    private function resolveAllowedServices(User $user, PlatformIdentity $identity): Collection
    {
        $services = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('service_scope'))
            ->filter(fn ($serviceScope) => in_array($serviceScope, ['platform', 'supply', 'calculation'], true))
            ->unique()
            ->values();

        if ($identity->hasRealmRole('platform_operator')) {
            $services->push('platform');
        }

        return $services
            ->filter(fn ($serviceScope) => in_array($serviceScope, ['platform', 'supply', 'calculation'], true))
            ->unique()
            ->values();
    }
}
