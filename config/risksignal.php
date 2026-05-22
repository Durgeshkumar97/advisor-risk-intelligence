<?php

return [
    'lead_notifications' => [
        'admin_email' => env('RISKSIGNAL_ADMIN_LEAD_EMAIL'),
        'queue' => env('RISKSIGNAL_LEAD_MAIL_QUEUE', 'mail'),
    ],
];
