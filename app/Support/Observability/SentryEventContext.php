<?php

namespace App\Support\Observability;

use App\Models\User;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\Request;
use Sentry\Event;
use Sentry\EventHint;

final class SentryEventContext
{
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        if (! app()->bound('request')) {
            return $event;
        }

        $request = request();

        self::decorateRequest($event, $request);
        self::decorateActor($event, $request);

        return $event;
    }

    private static function decorateRequest(Event $event, Request $request): void
    {
        $event->setTag('service', (string) config('app.name'));
        $event->setTag('environment', app()->environment());

        $route = $request->route();
        if ($route !== null) {
            $routeName = $route->getName();
            if (is_string($routeName) && $routeName !== '') {
                $event->setTag('route_name', $routeName);
            }

            $event->setTag('route_uri', $route->uri());
        }

        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if ($requestId !== '') {
            $event->setExtra('request_id', $requestId);
        }

        $upstreamService = trim((string) $request->header('X-Service-Name', ''));
        if ($upstreamService !== '') {
            $event->setExtra('upstream_service', $upstreamService);
        }
    }

    private static function decorateActor(Event $event, Request $request): void
    {
        $identity = $request->attributes->get('platform_identity');
        if ($identity instanceof PlatformIdentity) {
            $event->setUser(array_filter([
                'id' => $identity->subject,
                'username' => $identity->preferredUsername,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $event->setExtra('auth_subject', $identity->subject);

            if ($identity->preferredUsername !== null && $identity->preferredUsername !== '') {
                $event->setExtra('preferred_username', $identity->preferredUsername);
            }
        }

        $user = $request->attributes->get('platform_user');
        if ($user instanceof User) {
            $event->setExtra('platform_user_id', $user->getKey());
        }
    }
}
