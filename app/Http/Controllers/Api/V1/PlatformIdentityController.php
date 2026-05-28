<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Identity\UserProjectionService;
use App\Services\Navigation\NavigationResolver;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformIdentityController extends Controller
{
    public function __construct(
        private readonly UserProjectionService $userProjectionService,
        private readonly NavigationResolver $navigationResolver,
    ) {}

    public function me(Request $request): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $user = $this->userProjectionService->syncFromIdentity($identity);
        $navigation = $this->navigationResolver->resolve($user);
        $roles = $this->userProjectionService->effectiveRoleCodes($user)->all();
        $permissions = $this->userProjectionService->effectivePermissionCodes($user)->all();

        return response()->json([
            'data' => [
                'identity' => [
                    'subject' => $identity->subject,
                    'email' => $user->email,
                    'name' => $user->name,
                    'realm_roles' => $identity->realmRoles,
                ],
                'profile' => [
                    'id' => $user->id,
                    'status' => $user->status,
                    'display_name' => $user->display_name,
                    'preferred_app' => $user->preferred_app,
                ],
                'access' => [
                    'pending_access' => $navigation['pending_access'],
                    'allowed_services' => $navigation['allowed_services'],
                    'blocked_services' => $navigation['blocked_services'],
                    'pending_services' => $navigation['pending_services'],
                ],
                'roles' => $roles,
                'permissions' => $permissions,
                'navigation' => [
                    'preferred_route' => $navigation['preferred_route'],
                ],
            ],
        ]);
    }

    public function navigation(Request $request): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $user = $this->userProjectionService->syncFromIdentity($identity);
        $navigation = $this->navigationResolver->resolve($user);

        return response()->json([
            'data' => $navigation,
        ]);
    }
}
