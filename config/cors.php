<?php

$allowedOrigins = array_values(array_unique(array_filter([
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://ibimskp.test',
    env('FRONTEND_URL'),
    ...array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ),
])));

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

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '^https:\/\/[a-z0-9-]+\.ngrok-free\.app$',
        '^https:\/\/[a-z0-9-]+\.ngrok\.app$',
        '^https:\/\/[a-z0-9-]+\.ngrok\.dev$',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
