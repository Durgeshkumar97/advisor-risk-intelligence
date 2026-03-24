<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    | PostgreSQL is the single source of truth across local + production.
    | No SQLite/MySQL usage outside edge-case testing.
    */
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | PostgreSQL (PRIMARY)
        |--------------------------------------------------------------------------
        | Local:
        |   createdb risklens
        |
        | Production:
        |   Managed via Laravel Cloud (auto-injected env vars)
        |
        | Notes:
        |   - UUID supported natively
        |   - SSL auto-handled in production
        */
        'pgsql' => [
            'driver'   => 'pgsql',
            'url'      => env('DB_URL'),

            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'risklens'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),

            'charset'  => 'utf8',
            'prefix'   => '',
            'prefix_indexes' => true,

            'search_path' => 'public',

            /*
            | SSL:
            | - prefer → local works without SSL
            | - require → enforce encrypted connections
            */
            'sslmode' => env('DB_SSLMODE', 'prefer'),

            /*
            | Performance + Safety (IMPORTANT)
            */
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => 5, // fail fast on slow connections
            ]) : [],
        ],

        /*
        |--------------------------------------------------------------------------
        | SQLite (Testing Only)
        |--------------------------------------------------------------------------
        */
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
            'foreign_key_constraints' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Fallback Drivers (DO NOT USE)
        |--------------------------------------------------------------------------
        | Kept only to avoid package-level crashes.
        */
        'mysql' => [
            'driver' => 'mysql',
        ], 

        'mariadb' => [
            'driver' => 'mariadb',
        ], 

        'sqlsrv' => [
            'driver' => 'sqlsrv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Table
    |--------------------------------------------------------------------------
    */ 
    'migrations' => [
        'table' => 'migrations',
    ], 

    /*
    |--------------------------------------------------------------------------
    | Redis (Future Use: Queues, Caching)
    |--------------------------------------------------------------------------
    | Activate when:
    |   - Queue system enabled
    |   - WhatsApp automation live
    */
    'redis' => [ 

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'prefix' => Str::slug(env('APP_NAME', 'risklens')) . '-',
        ],

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ], 
    ],

]; 