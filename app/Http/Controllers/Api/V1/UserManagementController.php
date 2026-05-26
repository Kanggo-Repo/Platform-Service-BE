<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Registration\RegistrationPolicyService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly RegistrationPolicyService $registrationPolicyService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = trim((string) $request->input('role'));
            $query->whereHas('roles', function ($builder) use ($role): void {
                $builder->where('name', $role);
            });
        }

        $users = $query->orderBy('name')->get();
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        return response()->json([
            'data' => [
                'items' => $users->map(fn (User $user) => $this->serializeUser($user))->values(),
                'roles' => $roles->map(fn (Role $role) => [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                    'users_count' => $role->users_count,
                ])->values(),
                'registration_enabled' => $this->registrationPolicyService->current()->registration_enabled,
                'summary' => [
                    'total_users' => User::query()->count(),
                    'with_roles' => User::query()->has('roles')->count(),
                    'pending_access' => User::query()->doesntHave('roles')->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $user = User::query()->create([
            'keycloak_subject' => null,
            'email' => $validated['email'],
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'status' => empty($validated['roles']) ? 'pending_access' : 'active',
            'preferred_app' => null,
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);

        $this->syncRoles($user, $validated['roles'] ?? []);

        return response()->json([
            'data' => $this->serializeUser($user->fresh('roles')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $this->validatePayload($request, $user);

        $this->syncRoles($user, $validated['roles'] ?? []);

        $user->update([
            'email' => $validated['email'],
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'status' => empty($validated['roles']) ? 'pending_access' : 'active',
        ]);

        return response()->json([
            'data' => $this->serializeUser($user->fresh('roles')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        if ($user->keycloak_subject !== null && $user->keycloak_subject === $identity->subject) {
            return response()->json([
                'message' => 'User aktif tidak dapat dihapus.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    private function validatePayload(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);
    }

    private function syncRoles(User $user, array $roleNames): void
    {
        $roleIds = Role::query()
            ->whereIn('name', array_values($roleNames))
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'keycloak_subject' => $user->keycloak_subject,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->roles->isNotEmpty() ? 'active' : 'pending_access',
            'roles' => $user->roles->pluck('name')->values()->all(),
        ];
    }
}
