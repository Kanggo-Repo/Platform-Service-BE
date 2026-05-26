<?php

namespace Tests\Support;

trait GeneratesPlatformTokens
{
    protected function bootPlatformTokenConfig(): void
    {
        config()->set([
            'platform_auth.issuer' => 'https://auth.example.test/realms/kanggo',
            'platform_auth.audience' => 'platform-service',
            'platform_auth.hmac_secret' => 'platform-test-secret',
        ]);
    }

    protected function issuePlatformToken(array $overrides = [], array $realmRoles = []): string
    {
        $this->bootPlatformTokenConfig();

        $claims = array_merge([
            'iss' => config('platform_auth.issuer'),
            'aud' => config('platform_auth.audience'),
            'sub' => 'kc-user-1',
            'email' => 'user@example.test',
            'name' => 'Platform User',
            'preferred_username' => 'platform.user',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
        ], $overrides);

        if ($realmRoles !== []) {
            $claims['realm_access'] = [
                'roles' => $realmRoles,
            ];
        }

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);

        $signature = hash_hmac('sha256', $signingInput, config('platform_auth.hmac_secret'), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
