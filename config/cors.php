<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins - SECURITY: No wildcards in production
    |--------------------------------------------------------------------------
    |
    | Configure allowed origins via CORS_ALLOWED_ORIGINS environment variable.
    | Wildcards (*) are only permitted in local development environments.
    |
    | Production example:
    | CORS_ALLOWED_ORIGINS=https://your-frontend.com,https://app.your-domain.com
    |
    | NEVER use: CORS_ALLOWED_ORIGINS=* in production
    |
    */
    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
        function ($origin) {
            // Prevent wildcard origins in production
            if (str_contains($origin, '*') && app()->environment('production')) {
                throw new \RuntimeException(
                    "SECURITY ERROR: Wildcard CORS origins are not allowed in production. " .
                    "Found: {$origin}. Please specify exact origins in CORS_ALLOWED_ORIGINS."
                );
            }
            
            // Only allow wildcards in local/testing environments
            return !str_contains($origin, '*') || app()->environment('local', 'testing');
        }
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-HMAC-Signature',
        'X-Nonce',
        'X-Timestamp',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [
        'X-Request-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];
