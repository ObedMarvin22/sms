<?php

namespace App\Support\Results;

/**
 * A suggested head's remark from a student's average, for the head to accept or change.
 * Early-years children (no average) get a general encouraging remark.
 */
final class HeadRemarks
{
    public static function suggest(?float $average): string
    {
        return match (true) {
            $average === null => 'A good term. Keep growing and enjoying learning.',
            $average >= 80 => 'Excellent results. Keep it up.',
            $average >= 65 => 'Very good work. Aim even higher next term.',
            $average >= 50 => 'Good effort. There is room to improve.',
            $average >= 40 => 'Fair. Needs to work harder next term.',
            default => 'Needs much more effort and support. Please see the class teacher.',
        };
    }
}
