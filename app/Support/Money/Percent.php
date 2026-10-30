<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Percentages as whole basis points (1250 = 12.5%), so they never need floats either.
 */
final class Percent
{
    public const HUNDRED = 10_000;

    /**
     * Reads "10", "12.5", "12.50%" (at most two decimal places).
     *
     * @throws InvalidArgumentException when the text is not a percentage
     */
    public static function parse(string $input): int
    {
        $text = rtrim(str_replace(' ', '', trim($input)), '%');

        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            throw new InvalidArgumentException("\"{$input}\" is not a percentage.");
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /**
     * "12.5%", "50%", "33.33%".
     */
    public static function format(int $basisPoints): string
    {
        return self::plain($basisPoints).'%';
    }

    /**
     * For a form field: "12.5", "50".
     */
    public static function plain(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = rtrim(str_pad((string) ($basisPoints % 100), 2, '0', STR_PAD_LEFT), '0');

        return $fraction === '' ? (string) $whole : "{$whole}.{$fraction}";
    }
}
