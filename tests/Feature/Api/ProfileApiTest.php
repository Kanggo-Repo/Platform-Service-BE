<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Identity\KeycloakAdminProvisioner;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('profile show returns current projected user profile with keycloak identity metadata and name parts', function () {
    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'name' => 'Platform User',
        'display_name' => 'Platform User',
        'status' => 'active',
        'preferred_app' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $user->roles()->sync([$role->id]);

    $this->withToken($this->issuePlatformToken([
        'email_verified' => true,
        'preferred_username' => 'platform.user',
        'given_name' => 'Platform',
        'family_name' => 'User',
        'name' => 'Platform User',
    ], ['super_admin']))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'user@example.test')
        ->assertJsonPath('data.name', 'Platform User')
        ->assertJsonPath('data.full_name', 'Platform User')
        ->assertJsonPath('data.first_name', 'Platform')
        ->assertJsonPath('data.last_name', 'User')
        ->assertJsonPath('data.roles.0', 'Super Admin')
        ->assertJsonPath('data.identity.provider', 'keycloak')
        ->assertJsonPath('data.identity.provider_label', 'Keycloak')
        ->assertJsonPath('data.identity.subject', 'kc-user-1')
        ->assertJsonPath('data.identity.username', 'platform.user')
        ->assertJsonPath('data.identity.preferred_username', 'platform.user')
        ->assertJsonPath('data.identity.realm_roles.0', 'super_admin')
        ->assertJsonPath('data.identity.email_verified', true);
});

test('profile show prefers blank last name from keycloak over stale local full name', function () {
    $user = User::factory()->create([
        'keycloak_subject' => 'kc-user-1',
        'email' => 'user@example.test',
        'name' => 'Platform User',
        'display_name' => 'Platform User',
        'status' => 'active',
        'preferred_app' => 'platform',
    ]);

    $role = Role::query()->create([
        'code' => 'super_admin',
        'name' => 'Super Admin',
        'is_system' => true,
        'is_deletable' => false,
    ]);
    $user->roles()->sync([$role->id]);

    $provisioner = Mockery::mock(KeycloakAdminProvisioner::class);
    $provisioner->shouldReceive('fetchUserProfile')
        ->once()
        ->with('kc-user-1')
        ->andReturn([
            'first_name' => 'Platform',
            'last_name' => null,
            'full_name' => 'Platform',
            'email' => 'user@example.test',
            'username' => 'platform.user',
            'email_verified' => true,
        ]);
    app()->instance(KeycloakAdminProvisioner::class, $provisioner);

    $this->withToken($this->issuePlatformToken([
        'email_verified' => true,
        'preferred_username' => 'platform.user',
        'given_name' => 'Platform',
        'name' => 'Platform User',
    ], ['super_admin']))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Platform')
        ->assertJsonPath('data.first_name', 'Platform')
        ->assertJsonPath('data.last_name', null);
});

test('profile update changes local name parts and forwards password reset to keycloak when provided', function () {
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
        ->with('kc-user-1', 'Updated', 'user@example.test', 'new-password-123', 'User');
    app()->instance(KeycloakAdminProvisioner::class, $provisioner);

    $this->withToken($this->issuePlatformToken([
        'name' => 'Platform User',
        'email' => 'user@example.test',
        'email_verified' => false,
        'given_name' => 'Platform',
        'family_name' => 'User',
    ]))->putJson('/api/v1/profile', [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated User')
        ->assertJsonPath('data.full_name', 'Updated User')
        ->assertJsonPath('data.first_name', 'Updated')
        ->assertJsonPath('data.last_name', 'User')
        ->assertJsonPath('data.identity.provider', 'keycloak')
        ->assertJsonPath('data.identity.subject', 'kc-user-1')
        ->assertJsonPath('data.identity.email_verified', false);

    $user->refresh();

    expect($user->name)->toBe('Updated User')
        ->and($user->display_name)->toBe('Updated User');
});

test('profile update clears keycloak last name when submitted empty', function () {
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
        ->with('kc-user-1', 'Updated', 'user@example.test', null, null);
    app()->instance(KeycloakAdminProvisioner::class, $provisioner);

    $this->withToken($this->issuePlatformToken([
        'name' => 'Platform User',
        'email' => 'user@example.test',
        'given_name' => 'Platform',
        'family_name' => 'User',
    ]))->putJson('/api/v1/profile', [
        'first_name' => 'Updated',
        'last_name' => '',
    ])->assertOk()
        ->assertJsonPath('data.full_name', 'Updated')
        ->assertJsonPath('data.last_name', null);

    $user->refresh();

    expect($user->name)->toBe('Updated')
        ->and($user->display_name)->toBe('Updated');
});
