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
    'copyright' => 'RBR Laravel API Doc',
];
