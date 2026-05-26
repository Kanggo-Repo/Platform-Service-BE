<?php

use App\Models\RegistrationPolicy;
use App\Models\Role;
use App\Models\User;
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
        ->assertJsonFragment(['email' => $activeUser->email])
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

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/users', [
            'name' => 'Supply Owner',
            'email' => 'supply.owner@example.test',
            'roles' => ['Platform Operator'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Supply Owner')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.roles.0', 'Platform Operator');

    $user = User::query()->where('email', 'supply.owner@example.test')->firstOrFail();

    expect($user->keycloak_subject)->toBeNull()
        ->and($user->roles->pluck('name')->all())->toBe(['Platform Operator']);
});
