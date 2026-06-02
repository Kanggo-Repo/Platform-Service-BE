<?php

namespace App\Services\Identity;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakAdminProvisioner
{
    public function provisionUser(string $name, string $email, string $password, ?string $lastName = null): string
    {
        $payload = [
            'username' => $email,
            'email' => $email,
            'enabled' => true,
            'emailVerified' => true,
            'firstName' => $name,
            'credentials' => [[
                'type' => 'password',
                'value' => $password,
                'temporary' => false,
            ]],
        ];

        if ($lastName !== null) {
            $payload['lastName'] = $lastName;
        }

        $response = $this->httpClient()
            ->withToken($this->adminAccessToken())
            ->post($this->adminUsersUrl(), $payload);

        if ($response->status() === 409) {
            return $this->findUserIdByEmail($email);
        }

        $response->throw();

        $location = (string) $response->header('Location');
        $userId = trim((string) basename($location));

        if ($userId !== '') {
            return $userId;
        }

        return $this->findUserIdByEmail($email);
    }

    public function updateUser(string $subject, string $name, string $email, ?string $password = null, ?string $lastName = null): void
    {
        $accessToken = $this->adminAccessToken();

        $payload = [
            'email' => $email,
            'enabled' => true,
            'emailVerified' => true,
            'firstName' => $name,
        ];

        if ($lastName !== null) {
            $payload['lastName'] = $lastName;
        }

        $this->httpClient()
            ->withToken($accessToken)
            ->put($this->adminUsersUrl().'/'.$subject, $payload)
            ->throw();

        if ($password === null || $password === '') {
            return;
        }

        $this->httpClient()
            ->withToken($accessToken)
            ->put($this->adminUsersUrl().'/'.$subject.'/reset-password', [
                'type' => 'password',
                'value' => $password,
                'temporary' => false,
            ])
            ->throw();
    }

    public function setRealmRegistrationEnabled(bool $enabled): void
    {
        $accessToken = $this->adminAccessToken();

        $realm = $this->httpClient()
            ->withToken($accessToken)
            ->get($this->adminRealmUrl())
            ->throw()
            ->json();

        if (! is_array($realm)) {
            throw new RuntimeException('Keycloak realm lookup returned an invalid response.');
        }

        $realm['registrationAllowed'] = $enabled;

        $this->httpClient()
            ->withToken($accessToken)
            ->put($this->adminRealmUrl(), $realm)
            ->throw();
    }

    private function findUserIdByEmail(string $email): string
    {
        $users = $this->httpClient()
            ->withToken($this->adminAccessToken())
            ->get($this->adminUsersUrl(), [
                'email' => $email,
                'exact' => 'true',
            ])
            ->throw()
            ->json();

        if (! is_array($users)) {
            throw new RuntimeException('Keycloak user lookup returned an invalid response.');
        }

        foreach ($users as $user) {
            $resolvedEmail = strtolower(trim((string) ($user['email'] ?? '')));

            if ($resolvedEmail === strtolower(trim($email))) {
                $userId = trim((string) ($user['id'] ?? ''));

                if ($userId !== '') {
                    return $userId;
                }
            }
        }

        throw new RuntimeException('Unable to resolve provisioned Keycloak user.');
    }

    private function adminAccessToken(): string
    {
        $clientId = trim((string) config('platform_auth.admin_client_id', ''));
        $clientSecret = trim((string) config('platform_auth.admin_client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Keycloak admin client credentials are not configured.');
        }

        return (string) $this->httpClient()
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])
            ->throw()
            ->json('access_token');
    }

    private function httpClient(): PendingRequest
    {
        return Http::withOptions([
            'verify' => $this->resolveVerifyOption(),
        ])->acceptJson();
    }

    private function resolveVerifyOption(): bool|string
    {
        $caBundle = trim((string) config('platform_auth.ca_bundle', ''));

        if ($caBundle !== '') {
            return $caBundle;
        }

        return (bool) config('platform_auth.verify_ssl', true);
    }

    private function tokenUrl(): string
    {
        return rtrim($this->baseUrl(), '/').'/realms/'.$this->realm().'/protocol/openid-connect/token';
    }

    private function adminUsersUrl(): string
    {
        return rtrim($this->baseUrl(), '/').'/admin/realms/'.$this->realm().'/users';
    }

    private function adminRealmUrl(): string
    {
        return rtrim($this->baseUrl(), '/').'/admin/realms/'.$this->realm();
    }

    private function baseUrl(): string
    {
        $issuer = trim((string) config('platform_auth.issuer', ''));
        $parts = parse_url($issuer);

        $scheme = trim((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        if ($scheme === '' || $host === '') {
            throw new RuntimeException('Keycloak issuer is not configured correctly.');
        }

        return $scheme.'://'.$host.$port;
    }

    private function realm(): string
    {
        $issuer = trim((string) config('platform_auth.issuer', ''));
        $path = trim((string) parse_url($issuer, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        $realmIndex = array_search('realms', $segments, true);
        $realm = $realmIndex !== false ? ($segments[$realmIndex + 1] ?? '') : '';

        if (! is_string($realm) || trim($realm) === '') {
            throw new RuntimeException('Unable to resolve Keycloak realm from issuer.');
        }

        return trim($realm);
    }
}
