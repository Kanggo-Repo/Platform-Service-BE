<?php

use Spatie\Health\ResultStores\InMemoryHealthResultStore;

return [
    'result_stores' => [
        InMemoryHealthResultStore::class,
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', false),
        'notifications' => [],
    ],

    'oh_dear_endpoint' => [
        'enabled' => false,
        'always_send_fresh_results' => true,
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => '/oh-dear-health-check-results',
    ],

    'theme' => 'light',
    'silence_health_queue_job' => true,
    'json_results_failure_status' => 503,
    'secret_token' => env('HEALTH_SECRET_TOKEN'),
];
