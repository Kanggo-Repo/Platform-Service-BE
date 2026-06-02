<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\KeycloakAdminProvisioner;
use App\Services\Identity\UserProjectionService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

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
        $authoritativeProfile = $this->loadAuthoritativeKeycloakProfile($user);

        return response()->json([
            'data' => $this->serializeUser($user, $identity, $authoritativeProfile),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
        ]);

        $user = $this->userProjectionService->syncFromIdentity($identity);
        $fullName = $this->buildFullName($validated['first_name'], $validated['last_name'] ?? null, $user->name);

        if ($user->keycloak_subject !== null) {
            $this->keycloakAdminProvisioner->updateUser(
                subject: $user->keycloak_subject,
                name: $validated['first_name'],
                email: $user->email,
                password: $validated['password'] ?? null,
                lastName: $validated['last_name'] ?? null,
            );
        }

        $user->update([
            'name' => $fullName,
            'display_name' => $fullName,
        ]);

        return response()->json([
            'data' => $this->serializeUser($user->fresh('roles'), $identity, [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'full_name' => $fullName,
            ]),
        ]);
    }

    private function serializeUser(User $user, PlatformIdentity $identity, array $overrides = []): array
    {
        $firstName = $this->resolveFirstName($user, $identity, $overrides);
        $lastName = $this->resolveLastName($user, $identity, $overrides);
        $fullName = $this->resolveFullName($user, $identity, $firstName, $lastName, $overrides);

        return [
            'id' => $user->id,
            'name' => $fullName,
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'status' => $user->status,
            'display_name' => $user->display_name,
            'preferred_app' => $user->preferred_app,
            'updated_at' => optional($user->updated_at)?->toIso8601String(),
            'updated_at_human' => optional($user->updated_at)?->format('d M Y, H:i'),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'identity' => [
                'provider' => 'keycloak',
                'provider_label' => 'Keycloak',
                'subject' => $user->keycloak_subject ?? $identity->subject,
                'username' => $identity->preferredUsername,
                'preferred_username' => $identity->preferredUsername,
                'realm_roles' => $identity->realmRoles,
                'email_verified' => array_key_exists('email_verified', $identity->claims)
                    ? (bool) $identity->claims['email_verified']
                    : null,
            ],
        ];
    }

    private function resolveFirstName(User $user, PlatformIdentity $identity, array $overrides): string
    {
        if (array_key_exists('first_name', $overrides)) {
            return trim((string) $overrides['first_name']);
        }

        if (array_key_exists('given_name', $identity->claims)) {
            return trim((string) ($identity->claims['given_name'] ?? ''));
        }

        $claim = trim((string) ($identity->claims['given_name'] ?? ''));

        if ($claim !== '') {
            return $claim;
        }

        [$firstName] = $this->splitName($user->name);

        return $firstName;
    }

    private function resolveLastName(User $user, PlatformIdentity $identity, array $overrides): ?string
    {
        if (array_key_exists('last_name', $overrides)) {
            $lastName = trim((string) ($overrides['last_name'] ?? ''));

            return $lastName !== '' ? $lastName : null;
        }

        if (array_key_exists('family_name', $identity->claims)) {
            $lastName = trim((string) ($identity->claims['family_name'] ?? ''));

            return $lastName !== '' ? $lastName : null;
        }

        $claim = trim((string) ($identity->claims['family_name'] ?? ''));

        if ($claim !== '') {
            return $claim;
        }

        [, $lastName] = $this->splitName($user->name);

        return $lastName;
    }

    private function resolveFullName(User $user, PlatformIdentity $identity, string $firstName, ?string $lastName, array $overrides): string
    {
        if (array_key_exists('full_name', $overrides)) {
            $fullName = trim((string) $overrides['full_name']);

            if ($fullName !== '') {
                return $fullName;
            }

            return $this->buildFullName($firstName, $lastName, $user->name);
        }

        if (array_key_exists('name', $identity->claims)) {
            $fullName = trim((string) ($identity->claims['name'] ?? ''));

            if ($fullName !== '') {
                return $fullName;
            }

            return $this->buildFullName($firstName, $lastName, $user->name);
        }

        $claim = trim((string) ($identity->claims['name'] ?? ''));

        if ($claim !== '') {
            return $claim;
        }

        return $this->buildFullName($firstName, $lastName, $user->name);
    }

    private function buildFullName(string $firstName, ?string $lastName, string $fallback): string
    {
        $fullName = trim($firstName.' '.trim((string) $lastName));

        return $fullName !== '' ? $fullName : trim($fallback);
    }

    private function splitName(?string $value): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        if ($normalized === '') {
            return ['', null];
        }

        $segments = explode(' ', $normalized);
        $firstName = array_shift($segments) ?? '';
        $lastName = $segments !== [] ? implode(' ', $segments) : null;

        return [$firstName, $lastName];
    }

    private function loadAuthoritativeKeycloakProfile(User $user): array
    {
        if ($user->keycloak_subject === null || $user->keycloak_subject === '') {
            return [];
        }

        try {
            return $this->keycloakAdminProvisioner->fetchUserProfile($user->keycloak_subject);
        } catch (Throwable) {
            return [];
        }
    }
}
