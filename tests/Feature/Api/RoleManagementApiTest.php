<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('super admin can read grouped permissions catalog', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('data.total', fn ($value) => is_int($value) && $value > 10)
        ->assertJsonPath('data.groups.0.key', 'dashboard')
        ->assertJsonFragment([
            'name' => 'materials.manage',
        ])
        ->assertJsonFragment([
            'name' => 'users.assign-roles',
        ]);
});

test('local super admin role can read grouped permissions catalog without realm role', function () {
    $permission = Permission::query()->create([
        'code' => 'roles.view',
        'name' => 'Lihat roles',
        'module' => 'roles',
        'description' => 'Melihat daftar role.',
        'service_scope' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'description' => 'Core role',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $role->permissions()->sync([$permission->id]);

    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'status' => 'active',
    ]);
    $user->roles()->sync([$role->id]);

    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('data.total', fn ($value) => is_int($value) && $value > 10);
});

test('super admin can create role and implied permissions are expanded', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/roles', [
            'code' => 'supply_admin',
            'name' => 'Supply Admin',
            'description' => 'Owner for supply operations',
            'permissions' => ['materials.manage', 'stores.view'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'supply_admin')
        ->assertJsonPath('data.name', 'Supply Admin');

    $roleId = $response->json('data.id');

    $role = Role::query()->with('permissions')->findOrFail($roleId);

    expect($role->permissions->pluck('code')->all())->toContain('materials.view');
    expect($role->permissions->pluck('code')->all())->toContain('materials.recycle-bin.delete');
    expect($role->permissions->pluck('code')->all())->toContain('stores.view');
});

test('super admin can update role permissions', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $role = Role::query()->create([
        'code' => 'ops_viewer',
        'name' => 'Ops Viewer',
        'description' => 'Initial role',
        'is_system' => false,
        'is_deletable' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/roles/{$role->id}", [
            'code' => 'ops_viewer',
            'name' => 'Ops Viewer Updated',
            'description' => 'Updated role',
            'permissions' => ['roles.manage'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ops Viewer Updated');

    $role->refresh()->load('permissions');

    expect($role->name)->toBe('Ops Viewer Updated');
    expect($role->permissions->pluck('code')->all())->toContain('roles.view');
    expect($role->permissions->pluck('code')->all())->toContain('roles.delete');
});

test('super admin role cannot be renamed', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'description' => 'Core role',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/roles/{$role->id}", [
            'code' => 'super_admin',
            'name' => 'Super Admin Renamed',
            'description' => 'Renamed role',
            'permissions' => ['roles.manage'],
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Super Admin role name cannot be changed.',
        ]);
});

test('core roles cannot be deleted', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'description' => 'Core role',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $this->withToken($token)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Core roles cannot be deleted.',
        ]);
});

test('super admin can list roles with users count and permissions', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $permission = Permission::query()->create([
        'code' => 'custom.view',
        'name' => 'Custom View',
        'module' => 'custom',
        'description' => 'Custom permission',
        'service_scope' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'custom_role',
        'name' => 'Custom Role',
        'description' => 'List test role',
        'is_system' => false,
        'is_deletable' => true,
    ]);

    $role->permissions()->sync([$permission->id]);

    $this->withToken($token)
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment([
            'code' => 'custom_role',
            'name' => 'Custom Role',
        ])
        ->assertJsonPath('data.summary.total_roles', fn ($value) => is_int($value) && $value >= 1);
});
