<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Policy\PermissionCatalogService;
use App\Services\Policy\RolePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function __construct(
        private readonly PermissionCatalogService $permissionCatalogService,
        private readonly RolePolicyService $rolePolicyService,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->permissionCatalogService->syncCatalog();

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'items' => $roles->map(fn (Role $role) => $this->serializeRole($role))->values(),
                'summary' => [
                    'total_roles' => Role::query()->count(),
                    'total_permissions' => Permission::query()->count(),
                    'assigned_users' => Role::query()->withCount('users')->get()->sum('users_count'),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->permissionCatalogService->syncCatalog();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')],
        ]);

        $role = Role::query()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'is_deletable' => true,
        ]);

        $this->rolePolicyService->assignPermissions($role, $validated['permissions'] ?? []);

        return response()->json([
            'data' => $this->serializeRole($role->fresh('permissions')),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->permissionCatalogService->syncCatalog();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('roles', 'code')->ignore($role->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')],
        ]);

        if ($role->name === 'Super Admin' && $validated['name'] !== 'Super Admin') {
            return response()->json([
                'message' => 'Super Admin role name cannot be changed.',
            ], 422);
        }

        $role->update([
            'code' => $validated['code'],
            'name' => $role->name === 'Super Admin' ? 'Super Admin' : $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->rolePolicyService->assignPermissions($role, $validated['permissions'] ?? []);

        return response()->json([
            'data' => $this->serializeRole($role->fresh('permissions')),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if (! $role->is_deletable) {
            return response()->json([
                'message' => 'Core roles cannot be deleted.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function permissions(): JsonResponse
    {
        $this->permissionCatalogService->syncCatalog();

        return response()->json([
            'data' => [
                'total' => Permission::query()->count(),
                'groups' => $this->permissionCatalogService->grouped(),
                'all' => Permission::query()->orderBy('code')->pluck('code')->all(),
            ],
        ]);
    }

    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'is_deletable' => $role->is_deletable,
            'users_count' => $role->users_count,
            'permissions' => $role->permissions->pluck('code')->values()->all(),
        ];
    }
}
