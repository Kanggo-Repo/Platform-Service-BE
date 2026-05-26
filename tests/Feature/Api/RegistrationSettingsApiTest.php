<?php

use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();
});

test('registration settings require authenticated platform operator', function () {
    $token = $this->issuePlatformToken();

    $this->withToken($token)
        ->getJson('/api/v1/settings/registration')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
});

test('platform operator can read registration settings', function () {
    $token = $this->issuePlatformToken([], ['platform_operator']);

    $this->withToken($token)
        ->getJson('/api/v1/settings/registration')
        ->assertOk()
        ->assertJsonPath('data.registration_enabled', false)
        ->assertJsonPath('data.approval_mode', 'admin_approval')
        ->assertJsonPath('data.default_new_user_status', 'pending_access');
});

test('platform operator can update registration settings', function () {
    $token = $this->issuePlatformToken([], ['platform_operator']);

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
});
