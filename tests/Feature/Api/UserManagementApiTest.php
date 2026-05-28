<?php

use App\Models\RegistrationPolicy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('platform operator can list users with roles and registration summary', function () {
    RegistrationPolicy::query()->create([
        'registration_enabled' => true,
        'approval_mode' => 'admin_approval',
        'default_new_user_status' => 'pending_access',
        'updated_by_subject' => 'seed',
    ]);

    $role = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $activeUser = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'status' => 'active',
    ]);
    $activeUser->roles()->sync([$role->id]);

    User::factory()->create([
        'keycloak_subject' => 'kc-user-2',
        'status' => 'pending_access',
    ]);

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('data.summary.total_users', 2)
        ->assertJsonPath('data.summary.with_roles', 1)
        ->assertJsonPath('data.summary.pending_access', 1)
        ->assertJsonPath('data.registration_enabled', true)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonFragment(['email' => 'user@example.test'])
        ->assertJsonFragment([
            'name' => 'Platform Operator',
            'users_count' => 1,
        ]);
});

test('platform operator can create user and assign roles', function () {
    $role = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    config()->set([
        'platform_auth.admin_client_id' => 'platform-admin-cli',
        'platform_auth.admin_client_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://auth.example.test/realms/kanggo/protocol/openid-connect/token' => Http::response([
            'access_token' => 'kc-admin-token',
        ]),
        'https://auth.example.test/admin/realms/kanggo/users' => Http::response([], 201, [
            'Location' => 'https://auth.example.test/admin/realms/kanggo/users/kc-created-user-1',
        ]),
    ]);

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/users', [
            'name' => 'Supply Owner',
            'email' => 'supply.owner@example.test',
            'password' => 'password123',
            'roles' => ['Platform Operator'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Supply Owner')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.roles.0', 'Platform Operator');

    $user = User::query()->where('email', 'supply.owner@example.test')->firstOrFail();

    expect($user->keycloak_subject)->toBe('kc-created-user-1')
        ->and($user->roles->pluck('name')->all())->toBe(['Platform Operator']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://auth.example.test/realms/kanggo/protocol/openid-connect/token'
            && $request->method() === 'POST'
            && $request['client_id'] === 'platform-admin-cli'
            && $request['client_secret'] === 'secret-value'
            && $request['grant_type'] === 'client_credentials';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://auth.example.test/admin/realms/kanggo/users'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer kc-admin-token')
            && $request['email'] === 'supply.owner@example.test'
            && $request['username'] === 'supply.owner@example.test'
            && $request['enabled'] === true
            && $request['credentials'][0]['type'] === 'password'
            && $request['credentials'][0]['value'] === 'password123';
    });
});

test('platform operator can provision existing local user to keycloak during update', function () {
    $role = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $user = User::factory()->create([
        'keycloak_subject' => null,
        'email' => 'legacy.user@example.test',
        'status' => 'pending_access',
    ]);

    config()->set([
        'platform_auth.admin_client_id' => 'platform-admin-cli',
        'platform_auth.admin_client_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://auth.example.test/realms/kanggo/protocol/openid-connect/token' => Http::response([
            'access_token' => 'kc-admin-token',
        ]),
        'https://auth.example.test/admin/realms/kanggo/users' => Http::response([], 201, [
            'Location' => 'https://auth.example.test/admin/realms/kanggo/users/kc-created-user-2',
        ]),
    ]);

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Legacy User',
            'email' => 'legacy.user@example.test',
            'password' => 'password123',
            'roles' => ['Platform Operator'],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $user->refresh();

    expect($user->keycloak_subject)->toBe('kc-created-user-2')
        ->and($user->roles->pluck('name')->all())->toBe(['Platform Operator']);
});

test('platform operator can update existing keycloak user into super admin role', function () {
    $operatorRole = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $superAdminRole = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    $user = User::factory()->create([
        'keycloak_subject' => 'kc-existing-user-1',
        'email' => 'legacy.user@example.test',
        'status' => 'active',
    ]);
    $user->roles()->sync([$operatorRole->id]);

    config()->set([
        'platform_auth.admin_client_id' => 'platform-admin-cli',
        'platform_auth.admin_client_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://auth.example.test/realms/kanggo/protocol/openid-connect/token' => Http::response([
            'access_token' => 'kc-admin-token',
        ]),
        'https://auth.example.test/admin/realms/kanggo/users/kc-existing-user-1' => Http::response([], 204),
        'https://auth.example.test/admin/realms/kanggo/users/kc-existing-user-1/reset-password' => Http::response([], 204),
    ]);

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Legacy User',
            'email' => 'legacy.user@example.test',
            'roles' => ['Super Admin'],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.roles.0', 'Super Admin');

    $user->refresh();

    expect($user->roles->pluck('name')->all())->toBe(['Super Admin']);

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://auth.example.test/admin/realms/kanggo/users/kc-existing-user-1' || $request->method() !== 'PUT') {
            return false;
        }

        $data = $request->data();

        return ($data['email'] ?? null) === 'legacy.user@example.test'
            && ($data['firstName'] ?? null) === 'Legacy User'
            && ! array_key_exists('username', $data);
    });
});
