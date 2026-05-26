<?php

namespace App\Http\Middleware;

use App\Support\Auth\PlatformIdentity;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOperatorRole
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var PlatformIdentity|null $identity */
        $identity = $request->attributes->get('platform_identity');

        if (! $identity instanceof PlatformIdentity || ! $identity->hasRealmRole('platform_operator')) {
            return new JsonResponse([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
