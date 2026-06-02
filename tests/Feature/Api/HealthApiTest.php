<?php

test('health endpoint is publicly accessible', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'healthy' => true,
            'service' => config('app.name'),
        ])
        ->assertJsonStructure([
            'status',
            'healthy',
            'service',
            'finished_at',
            'checks' => [
                ['name', 'label', 'status', 'summary'],
            ],
        ]);
});

test('health json endpoint is publicly accessible', function () {
    $this->getJson('/api/v1/health/json')
        ->assertOk()
        ->assertJsonStructure([
            'finishedAt',
            'checkResults',
        ]);
});
