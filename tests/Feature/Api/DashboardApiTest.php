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

test('dashboard endpoint exposes platform summary for operator users', function () {
    $operatorRole = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'description' => 'Platform operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);

    Permission::query()->create([
        'code' => 'users.view',
        'name' => 'users.view',
        'module' => 'users',
        'description' => 'View users',
        'service_scope' => 'platform',
    ]);

    $allowedUser = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'status' => 'active',
    ]);
    $allowedUser->roles()->attach($operatorRole);

    $pendingUser = User::factory()->create([
        'keycloak_subject' => 'kc-user-2',
        'email' => 'pending@example.test',
        'status' => 'pending_access',
    ]);

    ServiceAccess::query()->create([
        'user_id' => $allowedUser->id,
        'service_code' => 'platform',
        'access_status' => 'allowed',
    ]);
    ServiceAccess::query()->create([
        'user_id' => $allowedUser->id,
        'service_code' => 'supply',
        'access_status' => 'allowed',
    ]);
    ServiceAccess::query()->create([
        'user_id' => $allowedUser->id,
        'service_code' => 'calculation',
        'access_status' => 'pending',
    ]);
    ServiceAccess::query()->create([
        'user_id' => $pendingUser->id,
        'service_code' => 'platform',
        'access_status' => 'pending',
    ]);

    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.summary.total_users', 2)
        ->assertJsonPath('data.summary.role_count', 2)
        ->assertJsonPath('data.summary.permission_count', fn ($value) => is_int($value) && $value > 10)
        ->assertJsonPath('data.summary.pending_access_count', 1)
        ->assertJsonPath('data.summary.allowed_user_count', 1)
        ->assertJsonPath('data.chart.labels.0', 'Platform')
        ->assertJsonPath('data.chart.data.0', 1)
        ->assertJsonPath('data.chart.data.1', 1)
        ->assertJsonPath('data.chart.data.2', 1)
        ->assertJsonPath('data.service_matrix.platform', 1)
        ->assertJsonPath('data.service_matrix.supply', 1)
        ->assertJsonPath('data.service_matrix.calculation', 1);
});
