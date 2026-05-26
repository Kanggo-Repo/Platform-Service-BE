<?php

namespace App\Http\Middleware;

use App\Services\Identity\KeycloakTokenVerifier;
use App\Support\Auth\PlatformIdentity;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlatformToken
{
    public function __construct(
        private readonly KeycloakTokenVerifier $tokenVerifier,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $claims = $this->tokenVerifier->verify($bearerToken);
        } catch (RuntimeException) {
            return new JsonResponse([
                'message' => 'Invalid access token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $identity = PlatformIdentity::fromClaims($claims);

        $request->attributes->set('platform_identity', $identity);

        return $next($request);
    }
}
