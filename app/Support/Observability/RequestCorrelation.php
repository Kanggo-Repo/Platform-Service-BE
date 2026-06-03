<?php

namespace App\Support\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestCorrelation
{
    public const REQUEST_ID_HEADER = 'X-Request-Id';

    public const SERVICE_NAME_HEADER = 'X-Service-Name';

    public static function resolveRequestId(?Request $request = null): string
    {
        $request ??= self::boundRequest();

        $requestId = trim((string) ($request?->attributes->get('request_id')
            ?? $request?->header(self::REQUEST_ID_HEADER, '')));

        return $requestId !== '' ? $requestId : (string) Str::uuid();
    }

    public static function currentServiceName(): string
    {
        return trim((string) config('app.name', 'laravel'));
    }

    public static function incomingServiceName(?Request $request = null): ?string
    {
        $request ??= self::boundRequest();

        $serviceName = trim((string) ($request?->attributes->get('upstream_service')
            ?? $request?->header(self::SERVICE_NAME_HEADER, '')));

        return $serviceName !== '' ? $serviceName : null;
    }

    public static function bindToRequest(Request $request, string $requestId, ?string $serviceName = null, ?string $upstreamService = null): void
    {
        $request->attributes->set('request_id', $requestId);

        if (($serviceName ?? '') !== '') {
            $request->attributes->set('service_name', $serviceName);
        }

        if (($upstreamService ?? '') !== '') {
            $request->attributes->set('upstream_service', $upstreamService);
        }
    }

    public static function outgoingHeaders(?string $serviceName = null): array
    {
        $resolvedServiceName = trim($serviceName ?? self::currentServiceName());

        return array_filter([
            self::REQUEST_ID_HEADER => self::resolveRequestId(),
            self::SERVICE_NAME_HEADER => $resolvedServiceName,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    private static function boundRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }
}
