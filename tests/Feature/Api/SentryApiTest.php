<?php

use App\Support\Observability\SentryEventContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('sentry configuration uses the platform event context callback', function () {
    expect(config('sentry.before_send'))->toBe([SentryEventContext::class, 'beforeSend'])
        ->and(config('sentry.dsn'))->toBeNull()
        ->and(config('sentry.server_name'))->toBe(config('app.name'))
        ->and(config('sentry.ignore_exceptions'))->toContain(
            AuthenticationException::class,
            AuthorizationException::class,
            ValidationException::class,
            NotFoundHttpException::class,
            MethodNotAllowedHttpException::class,
            TokenMismatchException::class,
        );
});

test('sentry debug route is available in testing', function () {
    $this->getJson('/api/v1/debug/sentry')
        ->assertStatus(500);
});
