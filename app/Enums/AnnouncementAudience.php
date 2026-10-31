<?php

namespace App\Enums;

/**
 * Who an announcement is for.
 */
enum AnnouncementAudience: string
{
    case School = 'school';
    case Streams = 'streams';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Whole school',
            self::Streams => 'Chosen classes',
        };
    }
}
