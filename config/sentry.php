<?php

use App\Support\Observability\SentryEventContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    'dsn' => filter_var(env('SENTRY_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
        ? env('SENTRY_LARAVEL_DSN')
        : null,

    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
    'server_name' => env('SENTRY_SERVER_NAME', env('APP_NAME')),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),
    'send_default_pii' => filter_var(env('SENTRY_SEND_DEFAULT_PII', false), FILTER_VALIDATE_BOOLEAN),
    'before_send' => [SentryEventContext::class, 'beforeSend'],

    'ignore_exceptions' => [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
        TokenMismatchException::class,
    ],
];
