<?php

namespace App\Actions\Students\Import;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Residence;
use App\Support\Money\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads what schools really type into a spreadsheet (Pilot preparation step 2): stray spaces
 * from copied cells, other heading names, dates in words, two phones in one cell, names in
 * capitals, amounts with "GH₵". Anything it cannot read is passed on unchanged, so the normal
 * rules reject it with a clear message.
 */
final class StudentImportCells
{
    /**
     * Other headings schools use, as the reader writes them (lower case, spaces to underscores).
     * Only used when the template's own heading is not in the file.
     */
    public const ALIASES = [
        'admission_no' => 'admission_number', 'adm_no' => 'admission_number', 'admission' => 'admission_number',
        'firstname' => 'first_name', 'first' => 'first_name', 'given_name' => 'first_name',
        'surname' => 'last_name', 'lastname' => 'last_name', 'family_name' => 'last_name',
        'middle_name' => 'other_names', 'middle_names' => 'other_names', 'other_name' => 'other_names',
        'sex' => 'gender',
        'dob' => 'date_of_birth', 'birth_date' => 'date_of_birth', 'birthday' => 'date_of_birth',
        'date_admitted' => 'admitted_on', 'admission_date' => 'admitted_on', 'date_of_admission' => 'admitted_on',
        'form' => 'class', 'class_name' => 'class', 'grade' => 'class',
        'day_boarding' => 'residence', 'boarding' => 'residence', 'day_or_boarding' => 'residence',
        'parent_name' => 'guardian1_name', 'guardian_name' => 'guardian1_name', 'parent' => 'guardian1_name',
        'parent_phone' => 'guardian1_phone', 'guardian_phone' => 'guardian1_phone', 'parent_contact' => 'guardian1_phone', 'contact' => 'guardian1_phone',
        'parent_relationship' => 'guardian1_relationship', 'relationship' => 'guardian1_relationship',
        'balance' => 'opening_balance', 'arrears' => 'opening_balance', 'balance_bf' => 'opening_balance', 'outstanding_balance' => 'opening_balance', 'owing' => 'opening_balance',
    ];

    /**
     * A row keyed by the template's headings, whatever names the file used.
     *
     * @param  array<string|int, mixed>  $raw
     * @return array<string|int, mixed>
     */
    public static function withTemplateHeadings(array $raw): array
    {
        foreach (self::ALIASES as $alias => $column) {
            if (array_key_exists($alias, $raw) && ! array_key_exists($column, $raw)) {
                $raw[$column] = $raw[$alias];
            }
        }

        return $raw;
    }

    /**
     * A cell as trimmed text. Non-breaking and zero-width spaces (copied from the web or
     * WhatsApp) become plain spaces or vanish; runs of spaces become one. Whole numbers lose
     * Excel's ".0" (phones typed as numbers).
     */
    public static function clean(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }
        if (! is_scalar($value)) {
            return '';
        }

        $text = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}", "\t"], ' ', (string) $value);
        $text = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', $text);

        return trim((string) preg_replace('/ {2,}/', ' ', $text));
    }

    /**
     * A name typed all in capitals becomes "Title Case" ("AMA SERWAA" → "Ama Serwaa"). A name
     * with any lower-case letter is left exactly as typed (McCarthy, deGraft).
     */
    public static function tidyName(string $name): string
    {
        if (preg_match('/\p{Lu}.*\p{Lu}/u', $name) !== 1 || mb_strtoupper($name) !== $name) {
            return $name;
        }

        return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
    }

    /**
     * "0244123456 / 0201234567" (or with a comma, semicolon or "or"): the first is the phone,
     * the second the alternative phone.
     *
     * @return array{0: string, 1: string}
     */
    public static function phones(string $cell): array
    {
        $parts = preg_split('#\s*(?:/|,|;|\bor\b|&)\s*#i', $cell, 2) ?: [$cell];

        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * An Excel date cell, YYYY-MM-DD, DD/MM/YYYY (as written in Ghana), or a date in words
     * ("8 Sep 2015", "08-Sept-2015", "8th September 2015", "September 8, 2015"), as YYYY-MM-DD.
     * Two-digit years are not guessed. Anything else is passed on unchanged.
     */
    public static function date(string $value): string
    {
        if (preg_match('/^\d{4,5}$/', $value) === 1) {
            return ExcelDate::excelToDateTimeObject((int) $value)->format('Y-m-d');
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Words: drop "st/nd/rd/th", read "Sept" as "Sep", and only accept four-digit years.
        $words = preg_replace('/(\d)(st|nd|rd|th)\b/i', '$1', $value) ?? $value;
        $words = preg_replace('/\bsept\b/i', 'Sep', $words) ?? $words;
        if (preg_match('/\b\d{4}\b/', $words) === 1) {
            foreach (['j M Y', 'd M Y', 'j F Y', 'd F Y', 'j-M-Y', 'd-M-Y', 'j-F-Y', 'd-F-Y', 'M j, Y', 'F j, Y', 'M j Y', 'F j Y'] as $format) {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $words);
                if ($date !== false && $date->format($format) === $words) {
                    return $date->format('Y-m-d');
                }
                // Months are matched in any case ("8 SEP 2015").
                $date = DateTimeImmutable::createFromFormat('!'.$format, ucwords(strtolower($words)));
                if ($date !== false && strcasecmp($date->format($format), $words) === 0) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return $value;
    }

    public static function gender(string $value): string
    {
        return match (mb_strtolower($value)) {
            'f', 'female', 'girl' => Gender::Female->value,
            'm', 'male', 'boy' => Gender::Male->value,
            default => $value,
        };
    }

    public static function relationship(string $value): ?GuardianRelationship
    {
        $lower = mb_strtolower(trim($value));

        foreach (GuardianRelationship::cases() as $case) {
            if ($lower === $case->value || $lower === mb_strtolower($case->label()) || $lower === str_replace('_', ' ', $case->value)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Day or boarding; blank means day. Null if it is neither.
     */
    public static function residence(string $value): ?Residence
    {
        return match (mb_strtolower($value)) {
            '', 'd', 'day', 'day student', 'day scholar' => Residence::Day,
            'b', 'boarding', 'boarder', 'boarding student' => Residence::Boarding,
            default => null,
        };
    }

    /**
     * A balance brought forward in cedis, signed: "1,250.50" or "GH₵ 1,250" is owed;
     * "-200", "(200)" or "200 CR" is in credit. Blank or zero is null. False if unreadable.
     */
    public static function balance(string $value): Money|false|null
    {
        $text = trim(str_ireplace(['GH₵', 'GHS', 'GHC', '₵'], '', $value));
        if ($text === '' || preg_match('/^0+(\.0+)?$/', $text) === 1) {
            return null;
        }

        $credit = false;
        if (preg_match('/^\((.*)\)$/', $text, $m) === 1 || preg_match('/^-\s*(.*)$/', $text, $m) === 1 || preg_match('/^(.*?)\s*cr$/i', $text, $m) === 1) {
            $credit = true;
            $text = trim($m[1]);
        }

        try {
            $money = Money::parse($text);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $credit ? Money::pesewas(-$money->pesewas) : $money;
    }

    /**
     * "JHS 1A", "jhs1a" and "JHS 1 A" are the same class.
     */
    public static function key(string $label): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $label) ?? '');
    }
}
