<?php

namespace App\Enums;

/**
 * Notices a school can choose to send by SMS (in-app notices always go). Stored in the school's
 * settings as "sms_events".
 */
enum SmsEvent: string
{
    case Absence = 'absence';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Absence => 'A child is marked absent',
            self::Payment => 'A payment is received',
        };
    }
}
