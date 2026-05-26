<?php

namespace App\Services\Policy;

use App\Models\Permission;
use App\Support\Auth\PermissionRegistry;

class PermissionCatalogService
{
    public function syncCatalog(): void
    {
        foreach (PermissionRegistry::definitions() as $definition) {
            Permission::query()->updateOrCreate(
                ['code' => $definition['name']],
                [
                    'name' => $definition['label'],
                    'module' => $definition['module'],
                    'description' => $definition['description'],
                    'service_scope' => $this->serviceScopeForModule($definition['module']),
                ],
            );
        }
    }

    public function grouped(): array
    {
        return PermissionRegistry::grouped();
    }

    public function definitions(): array
    {
        return PermissionRegistry::definitions();
    }

    private function serviceScopeForModule(string $module): string
    {
        return match ($module) {
            'materials', 'stores', 'units', 'recommendations', 'store-search-radius' => 'supply',
            'work-items', 'calculations', 'work-taxonomy', 'projects' => 'calculation',
            default => 'platform',
        };
    }
}
