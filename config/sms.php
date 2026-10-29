<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | "log" writes each message to the application log instead of sending it.
    | The provider driver (Hubtel, Arkesel or mNotify) is added in Sprint 4.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

];
