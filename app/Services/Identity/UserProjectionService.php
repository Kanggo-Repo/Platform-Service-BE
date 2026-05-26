<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\ServiceAccess;
use App\Models\User;
use App\Services\Registration\RegistrationPolicyService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Support\Facades\DB;

class UserProjectionService
{
    public function __construct(
        private readonly RegistrationPolicyService $registrationPolicyService,
    ) {
    }

    public function syncFromIdentity(PlatformIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $user = User::query()->firstWhere('keycloak_subject', $identity->subject);
            $policy = $this->registrationPolicyService->current();

            if ($user === null) {
                $user = User::query()
                    ->whereNull('keycloak_subject')
                    ->where('email', $identity->email)
                    ->first();
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

                $this->bootstrapServiceAccesses($user);

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

            $this->bootstrapServiceAccesses($user);

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

    private function bootstrapServiceAccesses(User $user): void
    {
        foreach (['platform', 'supply', 'calculation'] as $serviceCode) {
            ServiceAccess::query()->firstOrCreate([
                'user_id' => $user->id,
                'service_code' => $serviceCode,
            ], [
                'access_status' => 'pending',
            ]);
        }
    }
}
