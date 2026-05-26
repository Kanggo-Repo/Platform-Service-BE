<?php

namespace App\Services\Policy;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Auth\PermissionRegistry;

class RolePolicyService
{
    public function __construct(
        private readonly PermissionCatalogService $permissionCatalogService,
    ) {
    }

    public function syncCatalog(): void
    {
        $this->permissionCatalogService->syncCatalog();
    }

    public function assignPermissions(Role $role, array $requestedPermissions): void
    {
        $permissionCodes = $role->name === 'Super Admin'
            ? Permission::query()->pluck('code')->all()
            : PermissionRegistry::expand($requestedPermissions);

        $permissionIds = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }
}
