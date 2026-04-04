<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | MySQL (PRIMARY - Hostinger Compatible)
        |--------------------------------------------------------------------------
        */

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),

            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),

            'unix_socket' => env('DB_SOCKET', ''),

            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',

            'prefix' => '',
            'prefix_indexes' => true,

            'strict' => true,
            'engine' => null,

            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_TIMEOUT => 5,
            ]) : [],
        ],

        /*
        |--------------------------------------------------------------------------
        | SQLite (Optional - Testing)
        |--------------------------------------------------------------------------
        */

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | PostgreSQL (Optional - Future Use)
        |--------------------------------------------------------------------------
        */

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),

            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),

            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,

            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        /*
        |--------------------------------------------------------------------------
        | SQL Server (Optional)
        |--------------------------------------------------------------------------
        */

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),

            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'sa'),
            'password' => env('DB_PASSWORD', ''),

            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'table' => 'migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connections
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'prefix' => Str::slug(env('APP_NAME', 'laravel')) . '-',
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];