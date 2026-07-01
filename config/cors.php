<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Two clients hit this API:
    |   1. Flutter mobile app — native, no CORS restriction, but kept here for
    |      completeness and for any WebView usage.
    |   2. STATRA Check-in web app — runs in a browser, CORS is enforced.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:5173',
        'http://localhost:4173',
        'https://statra.health',
        'https://www.statra.health',
        'https://statrahealth.com',
        'https://www.statrahealth.com',
        'https://admin.statrahealth.com',
        'https://statra-website.vercel.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
