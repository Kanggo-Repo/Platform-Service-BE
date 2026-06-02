<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('registration settings require authenticated super admin', function () {
    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/settings/registration')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

test('super admin can read registration settings', function () {
    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->getJson('/api/v1/settings/registration')
        ->assertOk()
        ->assertJsonPath('data.registration_enabled', false)
        ->assertJsonPath('data.approval_mode', 'admin_approval')
        ->assertJsonPath('data.default_new_user_status', 'pending_access');
});

test('super admin can update registration settings and sync keycloak realm self registration', function () {
    config()->set([
        'platform_auth.admin_client_id' => 'platform-admin-cli',
        'platform_auth.admin_client_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://auth.example.test/realms/kanggo/protocol/openid-connect/token' => Http::response([
            'access_token' => 'kc-admin-token',
        ]),
        'https://auth.example.test/admin/realms/kanggo' => Http::sequence()
            ->push([
                'realm' => 'kanggo',
                'registrationAllowed' => false,
                'enabled' => true,
            ])
            ->push([], 204),
    ]);

    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->putJson('/api/v1/settings/registration', [
            'registration_enabled' => true,
            'approval_mode' => 'auto_approve',
            'default_new_user_status' => 'active',
            'notes' => 'Open registration for pilot rollout',
        ])
        ->assertOk()
        ->assertJsonPath('data.registration_enabled', true)
        ->assertJsonPath('data.approval_mode', 'auto_approve')
        ->assertJsonPath('data.default_new_user_status', 'active')
        ->assertJsonPath('data.notes', 'Open registration for pilot rollout');

    $this->withToken($token)
        ->getJson('/api/v1/settings/registration')
        ->assertOk()
        ->assertJsonPath('data.registration_enabled', true)
        ->assertJsonPath('data.approval_mode', 'auto_approve')
        ->assertJsonPath('data.default_new_user_status', 'active');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://auth.example.test/admin/realms/kanggo'
            && $request->method() === 'PUT'
            && ($request['registrationAllowed'] ?? null) === true
            && ($request['realm'] ?? null) === 'kanggo';
    });
});
