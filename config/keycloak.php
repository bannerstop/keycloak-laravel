<?php

return [
    // Base URL of the Keycloak server, the realm and the client of this application
    'server_url' => env('KEYCLOAK_SERVER_URL'),
    'realm' => env('KEYCLOAK_REALM'),
    'client_id' => env('KEYCLOAK_CLIENT_ID'),
    // Empty for public clients
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),

    'scopes' => ['openid', 'email', 'profile'],
    'allowed_algorithms' => ['RS256'],
    'leeway' => 30,

    // Cache store for the discovery document and the signing keys, null for the default store
    'cache_store' => null,

    // Class name of a PSR-18 client, null for Guzzle
    'http_client' => null,

    'login' => [
        // Empty allows every user of the realm
        'allowed_email_domains' => [],
        'require_verified_email' => true,
        // The guard users are logged in to after the Keycloak login
        'guard' => null,
        'redirect_to' => '/',
        // Where failed logins go; the reason is flashed as "keycloak_error"
        'failure_redirect_to' => '/',
        // Where Keycloak sends users after logout; register it as post logout redirect URI
        'logout_redirect_to' => '/',
        // Extra parameters for every login, e.g. ['kc_idp_hint' => 'corporate']
        'authorization_parameters' => [],
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'keycloak',
        'middleware' => ['web'],
    ],

    // Only mapped roles and groups are granted
    'roles' => [
        'default_roles' => ['user'],
        'realm_roles' => [
            // 'admin' => ['admin'],
        ],
        'client_roles' => [
            // 'my-app' => ['editor' => ['editor']],
        ],
        'groups' => [
            // '/staff/it' => ['it'],
        ],
    ],

    'bearer' => [
        // Audience access tokens must carry, null for the client id
        'audience' => null,
    ],

    // Class name of a UserProvisioner, null for session-only KeycloakUser objects
    'user_provisioner' => null,
];
