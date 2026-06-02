<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceAccess;
use App\Models\User;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('me endpoint requires bearer token', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('me endpoint rejects token with invalid issuer', function () {
    $token = $this->issuePlatformToken([
        'iss' => 'https://other.example.test/realms/unknown',
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid access token.',
        ]);
});

test('first authenticated request creates user projection and pending service access defaults', function () {
    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.identity.subject', 'kc-user-1')
        ->assertJsonPath('data.identity.email', 'user@example.test')
        ->assertJsonPath('data.profile.status', 'pending_access')
        ->assertJsonPath('data.access.pending_access', true)
        ->assertJsonPath('data.navigation.preferred_route', 'platform.access.pending');

    $user = User::query()->where('keycloak_subject', 'kc-user-1')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('user@example.test');
    expect($user->name)->toBe('Platform User');
    expect($user->status)->toBe('pending_access');

    expect(ServiceAccess::query()->where('user_id', $user->id)->count())->toBe(3);
    expect(ServiceAccess::query()->where('user_id', $user->id)->where('service_code', 'platform')->value('access_status'))->toBe('pending');
});

test('repeat authenticated request does not duplicate user projection', function () {
    $token = $this->issuePlatformToken();

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    expect(User::query()->where('keycloak_subject', 'kc-user-1')->count())->toBe(1);
    expect(ServiceAccess::query()->count())->toBe(3);
});

test('authenticated request updates projection when claims change', function () {
    $this->withToken($this->issuePlatformToken())
        ->getJson('/api/v1/me')
        ->assertOk();

    $updatedToken = $this->issuePlatformToken([
        'email' => 'updated@example.test',
        'name' => 'Updated User',
    ]);

    $this->withToken($updatedToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.identity.email', 'updated@example.test')
        ->assertJsonPath('data.identity.name', 'Updated User');

    $user = User::query()->where('keycloak_subject', 'kc-user-1')->firstOrFail();

    expect($user->email)->toBe('updated@example.test');
    expect($user->name)->toBe('Updated User');
});

test('authenticated request attaches keycloak subject to pre-provisioned user by email', function () {
    $user = User::factory()->create([
        'keycloak_subject' => null,
        'email' => 'user@example.test',
        'name' => 'Pre Provisioned User',
        'status' => 'active',
    ]);

    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.id', $user->id);

    $user->refresh();

    expect($user->keycloak_subject)->toBe('kc-user-1')
        ->and($user->name)->toBe('Platform User');
});

test('authenticated request activates pre-provisioned admin user with platform permissions', function () {
    $permission = Permission::query()->create([
        'code' => 'roles.manage',
        'name' => 'Kelola penuh roles',
        'module' => 'roles',
        'description' => 'Akses penuh role.',
        'service_scope' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $role->permissions()->sync([$permission->id]);

    $user = User::factory()->create([
        'keycloak_subject' => null,
        'email' => 'user@example.test',
        'name' => 'Admin Existing',
        'status' => 'active',
        'preferred_app' => 'platform',
    ]);
    $user->roles()->sync([$role->id]);

    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.id', $user->id)
        ->assertJsonPath('data.profile.status', 'active')
        ->assertJsonPath('data.access.pending_access', false)
        ->assertJsonPath('data.identity.realm_roles.0', 'super_admin')
        ->assertJsonPath('data.roles.0', 'super_admin')
        ->assertJsonPath('data.permissions.0', 'roles.manage')
        ->assertJsonPath('data.navigation.preferred_route', 'platform.dashboard');

    $user->refresh();

    expect($user->keycloak_subject)->toBe('kc-user-1');
    expect(ServiceAccess::query()
        ->where('user_id', $user->id)
        ->where('service_code', 'platform')
        ->value('access_status'))
        ->toBe('allowed');
});

test('me endpoint exposes effective local super admin role and permissions separately from keycloak realm roles', function () {
    $permission = Permission::query()->create([
        'code' => 'users.manage',
        'name' => 'Kelola penuh users',
        'module' => 'users',
        'description' => 'Akses penuh user.',
        'service_scope' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $role->permissions()->sync([$permission->id]);

    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'status' => 'active',
        'preferred_app' => 'platform',
    ]);
    $user->roles()->sync([$role->id]);

    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'platform',
        'access_status' => 'allowed',
    ]);
    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'supply',
        'access_status' => 'pending',
    ]);
    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'calculation',
        'access_status' => 'pending',
    ]);

    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.identity.realm_roles', [])
        ->assertJsonPath('data.roles.0', 'super_admin')
        ->assertJsonPath('data.permissions.0', 'users.manage')
        ->assertJsonPath('data.access.pending_access', false)
        ->assertJsonFragment(['allowed_services' => ['calculation', 'platform', 'supply']])
        ->assertJsonPath('data.navigation.preferred_route', 'platform.dashboard');
});

test('authenticated request grants platform access to pre-provisioned super admin from realm role', function () {
    $user = User::factory()->create([
        'keycloak_subject' => null,
        'email' => 'user@example.test',
        'name' => 'Realm Admin',
        'status' => 'pending_access',
        'preferred_app' => 'platform',
    ]);

    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.id', $user->id)
        ->assertJsonPath('data.profile.status', 'active')
        ->assertJsonPath('data.access.pending_access', false)
        ->assertJsonFragment(['allowed_services' => ['calculation', 'platform', 'supply']])
        ->assertJsonPath('data.navigation.preferred_route', 'platform.dashboard');

    $user->refresh();

    expect($user->keycloak_subject)->toBe('kc-user-1')
        ->and($user->status)->toBe('active');
    expect(ServiceAccess::query()
        ->where('user_id', $user->id)
        ->where('service_code', 'platform')
        ->value('access_status'))
        ->toBe('allowed');
});

test('first pre-provisioned local user stays pending when no local assignments exist and no super admin realm role is present', function () {
    $user = User::factory()->create([
        'keycloak_subject' => null,
        'email' => 'user@example.test',
        'name' => 'Bootstrap Admin',
        'status' => 'pending_access',
        'preferred_app' => 'platform',
    ]);

    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.profile.id', $user->id)
        ->assertJsonPath('data.profile.status', 'pending_access')
        ->assertJsonPath('data.access.pending_access', true)
        ->assertJsonFragment(['allowed_services' => []])
        ->assertJsonPath('data.navigation.preferred_route', 'platform.access.pending');

    $user->refresh()->load('roles');

    expect($user->roles->pluck('code')->all())->not->toContain('super_admin');
    expect(ServiceAccess::query()
        ->where('user_id', $user->id)
        ->where('service_code', 'platform')
        ->value('access_status'))
        ->toBe('pending');
});

test('navigation endpoint exposes pending service matrix', function () {
    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/navigation')
        ->assertOk()
        ->assertJsonPath('data.pending_access', true)
        ->assertJsonPath('data.preferred_route', 'platform.access.pending')
        ->assertJsonCount(3, 'data.services');
});

test('navigation endpoint resolves preferred app from allowed access matrix', function () {
    $token = $this->issuePlatformToken();

    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'preferred_app' => 'supply',
        'status' => 'active',
    ]);

    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'platform',
        'access_status' => 'allowed',
    ]);

    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'supply',
        'access_status' => 'allowed',
    ]);

    ServiceAccess::query()->create([
        'user_id' => $user->id,
        'service_code' => 'calculation',
        'access_status' => 'pending',
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/navigation')
        ->assertOk()
        ->assertJsonPath('data.pending_access', false)
        ->assertJsonPath('data.preferred_app', 'supply')
        ->assertJsonPath('data.preferred_route', 'service.supply')
        ->assertJsonPath('data.allowed_services.0', 'platform')
        ->assertJsonPath('data.allowed_services.1', 'supply');
});
