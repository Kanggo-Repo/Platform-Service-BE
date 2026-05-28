<?php

return [
    'issuer' => env('KEYCLOAK_ISSUER'),
    'audience' => env('KEYCLOAK_EXPECTED_AUDIENCE', 'platform-service'),
    'jwks_url' => env('KEYCLOAK_JWKS_URL'),
    'public_key' => env('KEYCLOAK_PUBLIC_KEY'),
    'hmac_secret' => env('KEYCLOAK_HMAC_SECRET'),
    'jwks_cache_seconds' => (int) env('KEYCLOAK_JWKS_CACHE_SECONDS', 300),
    'verify_ssl' => env('KEYCLOAK_VERIFY_SSL', true),
    'ca_bundle' => env('KEYCLOAK_CA_BUNDLE'),
    'admin_client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID'),
    'admin_client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET'),
];
