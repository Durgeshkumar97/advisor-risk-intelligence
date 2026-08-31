<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'founder_email' => env('FOUNDER_EMAIL', 'founder@risksignal.in'),

    /*
    | Recipient for every generated risk report and bundle report. Must be
    | resolved through config() — never env() at runtime — because deploy.sh
    | runs `php artisan config:cache`, after which env() returns null for any
    | key not read from inside a config file.
    */
    'reports_notify_email' => env('REPORTS_NOTIFY_EMAIL', env('FOUNDER_EMAIL', 'founder@risksignal.in')),

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'risk_service' => [
        'url' => env('RISK_SERVICE_URL', 'http://127.0.0.1:8123'),
        // Was 2s: a serial per-symbol loop on the risk_service side meant any
        // portfolio with more than one cold-cache mid/small-cap stock blew this
        // budget and fell back to a flat default for every holding. risk_service
        // now parallelizes the batch, but keep the client-side budget generous
        // as a stopgap for whatever cold-cache tail remains.
        'timeout' => (float) env('RISK_SERVICE_TIMEOUT', 10),
    ],

];
