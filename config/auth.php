<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [

        // Customer (SaaS users)
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Admin (internal)
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        // Customers
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        // Admins
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset (ONLY users for now)
    |--------------------------------------------------------------------------
    */

    'passwords' => [

        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => 10800,
];