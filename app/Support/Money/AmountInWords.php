<?php

namespace App\Support\Money;

/**
 * Money in words for receipts: "One thousand one hundred Ghana cedis and fifty pesewas".
 * British style ("one hundred and five"), up to 999,999,999 cedis.
 */
final class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    public static function for(Money $money): string
    {
        $pesewas = abs($money->pesewas);
        $cedis = intdiv($pesewas, 100);
        $rest = $pesewas % 100;

        $parts = [];
        if ($cedis > 0) {
            $parts[] = self::number($cedis).($cedis === 1 ? ' Ghana cedi' : ' Ghana cedis');
        }
        if ($rest > 0) {
            $parts[] = self::number($rest).($rest === 1 ? ' pesewa' : ' pesewas');
        }

        $words = $parts === [] ? 'zero Ghana cedis' : implode(' and ', $parts);

        return ucfirst(($money->pesewas < 0 ? 'minus ' : '').$words);
    }

    private static function number(int $n): string
    {
        $groups = [1_000_000 => 'million', 1_000 => 'thousand'];
        $words = [];

        foreach ($groups as $size => $name) {
            if ($n >= $size) {
                $words[] = self::belowThousand(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            // "one thousand and five": "and" before a last part under a hundred.
            $words[] = ($words !== [] && $n < 100 ? 'and ' : '').self::belowThousand($n);
        }

        return implode(' ', $words);
    }

    private static function belowThousand(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::ONES[intdiv($n, 100)].' hundred';
            $n %= 100;
            if ($n > 0) {
                $words[] = 'and';
            }
        }
        if ($n >= 20) {
            $words[] = self::TENS[intdiv($n, 10)].($n % 10 > 0 ? '-'.self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $words[] = self::ONES[$n];
        }

        return implode(' ', $words);
    }
}
