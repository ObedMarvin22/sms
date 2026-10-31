<?php

namespace App\Enums;

/**
 * Why an SMS was sent. Absence and payment notices are chosen by each school (stored in its
 * settings as "sms_events"; in-app notices always go). Parent-app codes always go by SMS.
 */
enum SmsEvent: string
{
    case Absence = 'absence';
    case Payment = 'payment';
    case Otp = 'otp';

    public function label(): string
    {
        return match ($this) {
            self::Absence => 'A child is marked absent',
            self::Payment => 'A payment is received',
            self::Otp => 'Parent app code',
        };
    }

    /**
     * The notices a school may turn on or off (School profile → SMS).
     *
     * @return list<self>
     */
    public static function optional(): array
    {
        return [self::Absence, self::Payment];
    }

    public static function tryOptional(string $value): ?self
    {
        $event = self::tryFrom($value);

        return in_array($event, self::optional(), true) ? $event : null;
    }
}
