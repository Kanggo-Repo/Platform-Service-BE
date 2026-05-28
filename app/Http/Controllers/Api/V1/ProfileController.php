<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\KeycloakAdminProvisioner;
use App\Services\Identity\UserProjectionService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserProjectionService $userProjectionService,
        private readonly KeycloakAdminProvisioner $keycloakAdminProvisioner,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $user = $this->userProjectionService->syncFromIdentity($identity)->load('roles');

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
        ]);

        $user = $this->userProjectionService->syncFromIdentity($identity);

        if ($user->keycloak_subject !== null) {
            $this->keycloakAdminProvisioner->updateUser(
                subject: $user->keycloak_subject,
                name: $validated['name'],
                email: $user->email,
                password: $validated['password'] ?? null,
            );
        }

        $user->update([
            'name' => $validated['name'],
            'display_name' => $validated['name'],
        ]);

        return response()->json([
            'data' => $this->serializeUser($user->fresh('roles')),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'display_name' => $user->display_name,
            'preferred_app' => $user->preferred_app,
            'updated_at' => optional($user->updated_at)?->toIso8601String(),
            'updated_at_human' => optional($user->updated_at)?->format('d M Y, H:i'),
            'roles' => $user->roles->pluck('name')->values()->all(),
        ];
    }
}
