<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Identity\KeycloakAdminProvisioner;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('profile show returns current projected user profile', function () {
    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'name' => 'Platform User',
        'display_name' => 'Platform User',
        'status' => 'active',
        'preferred_app' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'platform_operator',
        'name' => 'Platform Operator',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $user->roles()->sync([$role->id]);

    $this->withToken($this->issuePlatformToken())
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'user@example.test')
        ->assertJsonPath('data.name', 'Platform User')
        ->assertJsonPath('data.roles.0', 'Platform Operator');
});

test('profile update changes local name and forwards password reset to keycloak when provided', function () {
    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'name' => 'Platform User',
        'display_name' => 'Platform User',
        'status' => 'active',
    ]);

    $provisioner = Mockery::mock(KeycloakAdminProvisioner::class);
    $provisioner->shouldReceive('updateUser')
        ->once()
        ->with('kc-user-1', 'Updated User', 'user@example.test', 'new-password-123');
    app()->instance(KeycloakAdminProvisioner::class, $provisioner);

    $this->withToken($this->issuePlatformToken([
        'name' => 'Platform User',
        'email' => 'user@example.test',
    ]))->putJson('/api/v1/profile', [
        'name' => 'Updated User',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated User');

    $user->refresh();

    expect($user->name)->toBe('Updated User')
        ->and($user->display_name)->toBe('Updated User');
});
