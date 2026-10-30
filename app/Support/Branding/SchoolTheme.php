<?php

namespace App\Support\Branding;

use App\Models\School;

/**
 * Turns a school's primary colour into Flux's accent CSS variables.
 *
 * Only a valid #RRGGBB value is ever written into CSS; anything else keeps the default theme.
 */
final class SchoolTheme
{
    public static function isValidHex(?string $colour): bool
    {
        return $colour !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $colour) === 1;
    }

    /**
     * Inline style for the <html> element, or null to keep the default theme.
     * (Inline style wins over both the light and the .dark theme rules.)
     */
    public static function cssVariables(?School $school): ?string
    {
        $colour = $school?->primary_colour;

        if (! self::isValidHex($colour)) {
            return null;
        }

        $colour = strtolower((string) $colour);

        return "--color-accent: {$colour}; --color-accent-content: {$colour}; --color-accent-foreground: ".self::textColourOn($colour).';';
    }

    /**
     * Black or white, whichever reads better on the given background (WCAG relative luminance).
     */
    public static function textColourOn(string $hex): string
    {
        $channels = array_map(function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        // Contrast with white vs with black; pick the higher.
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#000000';
    }
}
