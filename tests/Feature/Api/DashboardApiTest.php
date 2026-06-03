<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\GeneratesPlatformTokens;

uses(GeneratesPlatformTokens::class);

beforeEach(function () {
    $this->bootPlatformTokenConfig();

    config()->set([
        'services.supply_service.base_url' => 'http://127.0.0.1:8008',
        'services.supply_service.service_name' => 'platform-service-be',
        'services.supply_service.token' => 'platform-be-test-token',
        'services.calculation_service.base_url' => 'http://127.0.0.1:8000',
    ]);
});

test('dashboard endpoint aggregates real summary from supply and calculation services', function () {
    Http::fake([
        'http://127.0.0.1:8008/api/v1/dashboard-summary' => Http::response([
            'data' => [
                'material_count' => 288,
                'unit_count' => 26,
                'store_count' => 126,
                'chart_data' => [
                    'labels' => ['Bata', 'Cat', 'Semen', 'Nat', 'Pasir', 'Keramik'],
                    'data' => [9, 85, 165, 0, 12, 11],
                ],
                'recent_activities' => [
                    [
                        'name' => 'Roman Glossy',
                        'category' => 'Keramik',
                        'category_color' => 'primary',
                        'created_at_human' => '1 menit yang lalu',
                    ],
                ],
            ],
        ]),
        'http://127.0.0.1:8000/api/v1/dashboard-summary' => Http::response([
            'data' => [
                'work_item_count' => 42,
            ],
        ]),
    ]);

    $token = $this->issuePlatformToken([], ['super_admin']);

    $this->withToken($token)
        ->withHeader('X-Request-Id', 'platform-phase-5-request')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertHeader('X-Request-Id', 'platform-phase-5-request')
        ->assertJsonPath('data.summary.total_users', 288)
        ->assertJsonPath('data.summary.role_count', 26)
        ->assertJsonPath('data.summary.permission_count', 126)
        ->assertJsonPath('data.summary.pending_access_count', 42)
        ->assertJsonPath('data.chart.labels.0', 'Bata')
        ->assertJsonPath('data.chart.data.2', 165)
        ->assertJsonPath('data.recent_activities.0.category', 'Keramik');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:8008/api/v1/dashboard-summary'
            && $request->hasHeader('X-Request-Id', 'platform-phase-5-request')
            && $request->hasHeader('X-Service-Name', 'platform-service-be')
            && $request->hasHeader('X-Service-Token', 'platform-be-test-token');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:8000/api/v1/dashboard-summary'
            && $request->hasHeader('X-Request-Id', 'platform-phase-5-request')
            && $request->hasHeader('X-Service-Name', 'platform-service-be');
    });
});
