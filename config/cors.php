<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The only CORS-relevant route is POST /api/v1/trial-start (and its
    | legacy alias), a public unauthenticated endpoint. Restricting origins
    | still matters: the framework default of '*' also covers
    | sanctum/csrf-cookie, and a wildcard lets any site script requests
    | against these paths from a visitor's browser.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'https://risksignal.in,https://www.risksignal.in'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
