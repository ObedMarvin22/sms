<?php

namespace App\Actions\Sms;

use App\Enums\SmsEvent;
use App\Models\School;
use App\Support\Settings\SchoolSettings;

/**
 * A school's SMS choices, read from its settings.
 */
final class SmsSettings
{
    public const DEFAULT_THRESHOLD = 100;

    public static function enabled(School $school, SmsEvent $event): bool
    {
        return in_array($event->value, (array) SchoolSettings::for($school)->get('sms_events', []), true);
    }

    /**
     * Warn finance staff when credit falls below this many units.
     */
    public static function threshold(School $school): int
    {
        return max(0, (int) SchoolSettings::for($school)->get('sms_low_credit_threshold', self::DEFAULT_THRESHOLD));
    }

    /**
     * The school's approved sender ID, or the platform default until it has one.
     */
    public static function sender(School $school): string
    {
        return $school->sms_sender_id ?? (string) config('sms.default_sender');
    }
}
