<?php

return [
    'default' => 'mobile-app',

    'documentations' => [

        // ── Mobile App docs ─────────────────────────────────────────────────
        'mobile-app' => [
            'api' => [
                'title' => 'SCD Wellness — Mobile App API',
            ],
            'routes' => [
                'api'             => 'api/v1/docs/mobile-app.json',
                'docs'            => 'docs/mobile-app',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware'      => ['api' => [], 'asset' => [], 'docs' => [], 'oauth2_callback' => []],
                'group_options'   => [],
            ],
            'paths' => [
                'docs'                   => storage_path('api-docs/mobile-app'),
                'docs_json'              => 'mobile-app-api-docs.json',
                'docs_yaml'              => 'mobile-app-api-docs.yaml',
                'annotations'            => [base_path('app/OpenApi/mobile-app')],
                'views'                  => base_path('resources/views/vendor/l5-swagger'),
                'base'                   => null,
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/l5-swagger/'),
                'excludes'               => [],
            ],
            'ui' => [
                'display' => [
                    'doc_expansion'   => 'none',
                    'filter'          => true,
                    'show_extensions' => true,
                ],
                'authorization' => [
                    'persist_authorization' => true,
                    'oauth2' => ['use_pkce_with_authorization_code_grant' => false],
                ],
            ],
            'security'            => [],
            'security_definitions' => ['name' => 'bearerAuth', 'in' => 'header', 'scopes' => []],
            'oauth2'              => [
                'default_flow' => 'implicit',
                'auth_url'     => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000') . '/oauth/authorize',
                'token_url'    => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000') . '/oauth/token',
                'scopes'       => [],
            ],
            'scan'    => ['exclude' => []],
            'schemes' => [],
            'constants' => ['L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000')],
        ],

        // ── Check-in docs ────────────────────────────────────────────────────
        'check-in' => [
            'api' => [
                'title' => 'STATRA — Check-in Web App API',
            ],
            'routes' => [
                'api'             => 'api/v1/docs/check-in.json',
                'docs'            => 'docs/check-in',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware'      => ['api' => [], 'asset' => [], 'docs' => [], 'oauth2_callback' => []],
                'group_options'   => [],
            ],
            'paths' => [
                'docs'                   => storage_path('api-docs/check-in'),
                'docs_json'              => 'check-in-api-docs.json',
                'docs_yaml'              => 'check-in-api-docs.yaml',
                'annotations'            => [base_path('app/OpenApi/check-in')],
                'views'                  => base_path('resources/views/vendor/l5-swagger'),
                'base'                   => null,
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/l5-swagger/'),
                'excludes'               => [],
            ],
            'ui' => [
                'display' => [
                    'doc_expansion'   => 'none',
                    'filter'          => true,
                    'show_extensions' => true,
                ],
                'authorization' => [
                    'persist_authorization' => true,
                    'oauth2' => ['use_pkce_with_authorization_code_grant' => false],
                ],
            ],
            'security'            => [],
            'security_definitions' => ['name' => 'bearerAuth', 'in' => 'header', 'scopes' => []],
            'oauth2'              => [
                'default_flow' => 'implicit',
                'auth_url'     => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000') . '/oauth/authorize',
                'token_url'    => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000') . '/oauth/token',
                'scopes'       => [],
            ],
            'scan'    => ['exclude' => []],
            'schemes' => [],
            'constants' => ['L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000')],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs'            => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware'      => ['api' => [], 'asset' => [], 'docs' => [], 'oauth2_callback' => []],
            'group_options'   => [],
        ],
        'paths' => [
            'docs'                   => storage_path('api-docs'),
            'docs_json'              => 'api-docs.json',
            'docs_yaml'              => 'api-docs.yaml',
            'annotations'            => [base_path('app')],
            'views'                  => base_path('resources/views/vendor/l5-swagger'),
            'base'                   => null,
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/l5-swagger/'),
            'excludes'               => [],
        ],
        'ui' => [
            'display' => [
                'doc_expansion'   => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter'          => env('L5_SWAGGER_UI_FILTERS', true),
                'show_extensions' => true,
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),
                'oauth2' => ['use_pkce_with_authorization_code_grant' => false],
            ],
        ],
        'security' => [],
        'schemes'  => [],
    ],
];
