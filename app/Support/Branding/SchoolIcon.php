<?php

namespace App\Support\Branding;

use App\Models\School;
use RuntimeException;

/**
 * The parent app's home-screen icon: the school's initials in white on its main colour, drawn
 * with GD (no Node or image tools on shared hosting). School logos stay private, so they are
 * not used here.
 */
final class SchoolIcon
{
    public const SIZES = [192, 512];

    public const DEFAULT_COLOUR = '#ea580c'; // Orange Tree orange

    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    /**
     * Up to two letters: the first letters of the first two words, e.g. "Greenhill Academy" → "GA".
     */
    public static function initials(School $school): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $school->name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }

    public static function colour(School $school): string
    {
        return SchoolTheme::isValidHex($school->primary_colour) ? strtolower((string) $school->primary_colour) : self::DEFAULT_COLOUR;
    }

    /**
     * PNG bytes for a square icon. Content stays inside the middle 80%, so Android can crop it
     * to a circle ("maskable").
     */
    public static function png(School $school, int $size): string
    {
        if (! in_array($size, self::SIZES, true)) {
            throw new RuntimeException("Unsupported icon size {$size}.");
        }

        $image = imagecreatetruecolor($size, $size);
        $hex = ltrim(self::colour($school), '#');
        $channel = fn (int $at): int => max(0, min(255, (int) hexdec(substr($hex, $at, 2))));
        $text = SchoolTheme::textColourOn(self::colour($school)) === '#ffffff' ? 255 : 0;

        imagefill($image, 0, 0, (int) imagecolorallocate($image, $channel(0), $channel(2), $channel(4)));
        $ink = (int) imagecolorallocate($image, $text, $text, $text);

        $font = base_path(self::FONT);
        $initials = self::initials($school);
        $points = $size * (mb_strlen($initials) > 1 ? 0.30 : 0.40);
        $box = imagettfbbox($points, 0, $font, $initials) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $x = (int) (($size - ($box[2] - $box[0])) / 2 - $box[0]);
        $y = (int) (($size - ($box[1] - $box[7])) / 2 - $box[7]);
        imagettftext($image, $points, 0, $x, $y, $ink, $font, $initials);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
