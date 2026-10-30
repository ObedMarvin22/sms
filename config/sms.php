<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | "log" writes each message to storage/logs/notifications-*.log instead of sending it: use it
    | locally, in tests and on staging. "arkesel" really sends (and costs money): only Obed turns
    | it on, on production.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    // Sender name when a school has no approved sender ID yet (max 11 characters).
    'default_sender' => env('SMS_DEFAULT_SENDER', 'OrangeTree'),

    'arkesel' => [
        'base_url' => env('ARKESEL_BASE_URL', 'https://sms.arkesel.com'),
        'api_key' => env('ARKESEL_API_KEY'),
    ],

];
