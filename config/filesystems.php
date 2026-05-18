<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | LOCAL
        |--------------------------------------------------------------------------
        |
        | Internal application storage.
        |
        */

        'local' => [

            'driver' => 'local',

            'root' => storage_path('app/private/portfolios'),

            'serve' => true,

            'throw' => false,

            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | PRIVATE PORTFOLIO STORAGE
        |--------------------------------------------------------------------------
        |
        | Secure storage for uploaded financial files.
        | NEVER publicly exposed.
        |
        */

        'portfolios' => [

            'driver' => 'local',

            'root' => storage_path('app/private/portfolios'),

            'throw' => false,

            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | PUBLIC STORAGE
        |--------------------------------------------------------------------------
        */

        'public' => [

            'driver' => 'local',

            'root' => storage_path('app/public'),

            'url' => rtrim(
                env('APP_URL', 'http://localhost'),
                '/'
            ) . '/storage',

            'visibility' => 'public',

            'throw' => false,

            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | AMAZON S3
        |--------------------------------------------------------------------------
        */

        's3' => [

            'driver' => 's3',

            'key' => env('AWS_ACCESS_KEY_ID'),

            'secret' => env('AWS_SECRET_ACCESS_KEY'),

            'region' => env('AWS_DEFAULT_REGION'),

            'bucket' => env('AWS_BUCKET'),

            'url' => env('AWS_URL'),

            'endpoint' => env('AWS_ENDPOINT'),

            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false
            ),

            'throw' => false,

            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [

        public_path('storage') => storage_path('app/public'),
    ],

];
