<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([env('APP_URL')]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-XSRF-TOKEN'
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,
];