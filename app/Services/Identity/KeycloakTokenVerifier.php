<?php

namespace App\Services\Identity;

use App\Support\Observability\RequestCorrelation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakTokenVerifier
{
    public function verify(string $token): array
    {
        [$header, $payload, $signature, $signingInput] = $this->parseToken($token);

        $issuer = (string) config('platform_auth.issuer');
        $audience = (string) config('platform_auth.audience');

        if (($payload['iss'] ?? null) !== $issuer) {
            throw new RuntimeException('Invalid issuer.');
        }

        $tokenAudience = $payload['aud'] ?? null;

        $isAudienceValid = match (true) {
            is_array($tokenAudience) => in_array($audience, $tokenAudience, true),
            is_string($tokenAudience) => $tokenAudience === $audience,
            default => false,
        };

        if (! $isAudienceValid) {
            throw new RuntimeException('Invalid audience.');
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Token expired.');
        }

        $algorithm = $header['alg'] ?? null;

        if ($algorithm === 'HS256') {
            $this->verifyHmacSignature($signingInput, $signature);
        } elseif ($algorithm === 'RS256') {
            $this->verifyRsaSignature($header, $signingInput, $signature);
        } else {
            throw new RuntimeException('Unsupported token algorithm.');
        }

        return $payload;
    }

    private function parseToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true, flags: JSON_THROW_ON_ERROR);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, flags: JSON_THROW_ON_ERROR);
        $signature = $this->base64UrlDecode($encodedSignature);

        return [$header, $payload, $signature, implode('.', [$encodedHeader, $encodedPayload])];
    }

    private function verifyHmacSignature(string $signingInput, string $signature): void
    {
        $secret = (string) config('platform_auth.hmac_secret');

        if ($secret === '') {
            throw new RuntimeException('HMAC secret is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', $signingInput, $secret, true);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid signature.');
        }
    }

    private function verifyRsaSignature(array $header, string $signingInput, string $signature): void
    {
        $publicKey = (string) config('platform_auth.public_key');

        if ($publicKey === '') {
            $publicKey = $this->resolvePublicKeyFromJwks((string) ($header['kid'] ?? ''));
        }

        if ($publicKey === '') {
            throw new RuntimeException('Public key is not available.');
        }

        $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new RuntimeException('Invalid signature.');
        }
    }

    private function resolvePublicKeyFromJwks(string $kid): string
    {
        $jwksUrl = (string) config('platform_auth.jwks_url');

        if ($jwksUrl === '' || $kid === '') {
            return '';
        }

        $cacheSeconds = (int) config('platform_auth.jwks_cache_seconds', 300);

        $jwks = Cache::remember(
            'platform_auth_jwks',
            $cacheSeconds,
            fn (): array => $this->httpClient()->acceptJson()->get($jwksUrl)->throw()->json('keys', []),
        );

        foreach ($jwks as $key) {
            if (($key['kid'] ?? null) !== $kid) {
                continue;
            }

            $certificate = $key['x5c'][0] ?? null;

            if (! is_string($certificate) || $certificate === '') {
                continue;
            }

            return "-----BEGIN CERTIFICATE-----\n"
                .chunk_split($certificate, 64, "\n")
                ."-----END CERTIFICATE-----\n";
        }

        return '';
    }

    private function httpClient(): PendingRequest
    {
        return Http::withOptions([
            'verify' => $this->resolveVerifyOption(),
        ])->withHeaders(RequestCorrelation::outgoingHeaders());
    }

    private function resolveVerifyOption(): bool|string
    {
        $caBundle = trim((string) config('platform_auth.ca_bundle', ''));

        if ($caBundle !== '') {
            return $caBundle;
        }

        return (bool) config('platform_auth.verify_ssl', true);
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'));
    }
}
