<?php

namespace App\Http\Middleware;

use App\Support\Observability\RequestCorrelation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestCorrelation
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestCorrelation::resolveRequestId($request);
        $serviceName = RequestCorrelation::currentServiceName();
        $upstreamService = RequestCorrelation::incomingServiceName($request);

        RequestCorrelation::bindToRequest($request, $requestId, $serviceName, $upstreamService);

        Context::add('request_id', $requestId);
        Context::add('service', $serviceName);
        Context::add('method', $request->method());
        Context::add('path', '/'.ltrim($request->path(), '/'));

        if ($upstreamService !== null) {
            Context::add('upstream_service', $upstreamService);
        }

        Log::shareContext(array_filter([
            'request_id' => $requestId,
            'service' => $serviceName,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'upstream_service' => $upstreamService,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $response = $next($request);
        $response->headers->set(RequestCorrelation::REQUEST_ID_HEADER, $requestId);

        return $response;
    }
}
