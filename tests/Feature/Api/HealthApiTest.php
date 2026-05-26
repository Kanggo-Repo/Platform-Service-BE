<?php

test('health endpoint is publicly accessible', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'platform-service',
        ]);
});
