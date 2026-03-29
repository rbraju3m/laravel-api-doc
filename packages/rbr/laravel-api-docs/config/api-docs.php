<?php

return [
    'title' => env('API_DOCS_TITLE', 'API Documentation'),
    'description' => env('API_DOCS_DESCRIPTION', 'Auto-generated API documentation'),
    'exclude_prefixes' => [
        '_ignition',
        '_debugbar',
        'sanctum',
        'docs/api',
        'docs/api/projects',
        'up',
    ],
    'exclude_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URI prefix for all API documentation routes.
    |
    */
    'route_prefix' => 'docs/api',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to the API documentation routes.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Copyright
    |--------------------------------------------------------------------------
    |
    | Copyright text displayed in the footer.
    |
    */
    'copyright' => 'RBR Laravel API Doc',
];
