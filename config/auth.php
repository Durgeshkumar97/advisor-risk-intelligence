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

        // Main SaaS users
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Internal admin dashboard
        'admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        // Shared users table
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    */

    'passwords' => [

        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            // 24 h — welcome set-password email needs a longer window than the
            // default 60 min; applies to all password resets (acceptable at launch).
            'expire' => 1440,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => 10800,

    /*
    |--------------------------------------------------------------------------
    | New-Password Policy
    |--------------------------------------------------------------------------
    |
    | Applied everywhere a user chooses a password (register, reset, change)
    | via Password::defaults() in AppServiceProvider, and shown to the user by
    | the <x-password-requirements> hint, so rule and hint never drift apart.
    | Composition rules (upper/lowercase, number, symbol) are a deliberate
    | product choice: Indian IFA users expect bank-style password rules, even
    | though NIST SP 800-63B-4 advises against them.
    |
    | max 64: bcrypt only reads the first 72 bytes, so longer input would be
    |         silently truncated; a separate 72-byte check covers multi-byte text.
    |
    */

    'password_policy' => [
        'min' => (int) env('PASSWORD_MIN_LENGTH', 12),
        'max' => 64,
        'mixed_case' => true,
        'numbers' => true,
        'symbols' => true,
        'check_breached' => (bool) env('PASSWORD_CHECK_BREACHED', true),
    ],
];
