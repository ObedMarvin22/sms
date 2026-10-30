<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to storage/logs/notifications-*.log instead of sending them. The driver for
 * local development, tests and staging.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message, string $sender): string
    {
        $id = 'log-'.Str::ulid();
        Log::channel('notifications')->info($message, ['to' => $to, 'sender' => $sender, 'id' => $id]);

        return $id;
    }
}
