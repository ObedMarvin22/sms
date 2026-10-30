<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case NotSent = 'not_sent';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Sending',
            self::Sent => 'Sent',
            self::Failed => 'Failed (units returned)',
            self::NotSent => 'Not sent: no credit',
        };
    }
}
