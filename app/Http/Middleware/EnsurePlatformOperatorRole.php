<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Identity\UserProjectionService;
use App\Support\Auth\PlatformIdentity;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOperatorRole
{
    public function __construct(
        private readonly UserProjectionService $userProjectionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var PlatformIdentity|null $identity */
        $identity = $request->attributes->get('platform_identity');

        if (! $identity instanceof PlatformIdentity) {
            return new JsonResponse([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($identity->hasRealmRole('super_admin')) {
            $request->attributes->set(
                'platform_user',
                $request->attributes->get('platform_user') ?? $this->userProjectionService->syncFromIdentity($identity),
            );

            return $next($request);
        }

        /** @var User $user */
        $user = $request->attributes->get('platform_user') ?? $this->userProjectionService->syncFromIdentity($identity);
        $request->attributes->set('platform_user', $user);

        if ($this->canManagePlatform($user)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'Forbidden.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function canManagePlatform(User $user): bool
    {
        $user->loadMissing('roles.permissions');

        $roleCodes = $user->roles
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        if (in_array('super_admin', $roleCodes, true)) {
            return true;
        }

        $permissionCodes = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('code'))
            ->filter()
            ->unique()
            ->values();

        return $this->hasAnyPlatformAdminPermission($permissionCodes);
    }

    private function hasAnyPlatformAdminPermission(Collection $permissionCodes): bool
    {
        return $permissionCodes->intersect([
            'dashboard.view',
            'roles.view',
            'roles.manage',
            'users.view',
            'users.manage',
            'settings.manage',
        ])->isNotEmpty();
    }
}
