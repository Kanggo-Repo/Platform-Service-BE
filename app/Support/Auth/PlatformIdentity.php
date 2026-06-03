<?php

namespace App\Support\Auth;

final class PlatformIdentity
{
    public function __construct(
        public readonly string $subject,
        public readonly ?string $email,
        public readonly ?string $name,
        public readonly ?string $preferredUsername,
        public readonly array $realmRoles,
        public readonly array $claims,
    ) {}

    public static function fromClaims(array $claims): self
    {
        return new self(
            subject: (string) ($claims['sub'] ?? ''),
            email: isset($claims['email']) ? (string) $claims['email'] : null,
            name: isset($claims['name']) ? (string) $claims['name'] : null,
            preferredUsername: isset($claims['preferred_username']) ? (string) $claims['preferred_username'] : null,
            realmRoles: array_values($claims['realm_access']['roles'] ?? []),
            claims: $claims,
        );
    }

    public function hasRealmRole(string $role): bool
    {
        return in_array($role, $this->realmRoles, true);
    }
}
