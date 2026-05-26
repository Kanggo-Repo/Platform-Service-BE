<?php

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
