<?php

namespace App\Support;

/**
 * Ghana phone numbers, stored as +233XXXXXXXXX (CLAUDE.md).
 */
final class Phone
{
    /**
     * Normalise a Ghanaian number, or return null if it is not one.
     *
     * Accepts 0244123456, 244123456, 233244123456, +233244123456, 00233…, with spaces,
     * dashes, dots or brackets. The 9 national digits must start with 2 (mobile and most
     * landlines), 3 (landlines) or 5 (mobile).
     */
    public static function normalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-().]/', '', trim($input)) ?? '';

        $national = match (true) {
            (bool) preg_match('/^\+233(\d{9})$/', $digits, $m) => $m[1],
            (bool) preg_match('/^00233(\d{9})$/', $digits, $m) => $m[1],
            (bool) preg_match('/^233(\d{9})$/', $digits, $m) => $m[1],
            (bool) preg_match('/^0(\d{9})$/', $digits, $m) => $m[1],
            (bool) preg_match('/^(\d{9})$/', $digits, $m) => $m[1],
            default => null,
        };

        if ($national === null || ! in_array($national[0], ['2', '3', '5'], true)) {
            return null;
        }

        return '+233'.$national;
    }
}
