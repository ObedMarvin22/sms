<?php

namespace App\Actions\Students;

use App\Enums\Gender;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Validation shared by the student Actions. Queries are school-scoped (BelongsToSchool).
 */
final class StudentRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{admission_number: ?string, first_name: string, last_name: string, other_names: ?string, gender: ?Gender, date_of_birth: ?CarbonImmutable, admitted_on: CarbonImmutable}
     */
    public static function details(array $input, ?Student $ignore = null): array
    {
        $errors = [];
        $text = function (string $key, int $max) use ($input, &$errors): ?string {
            $value = preg_replace('/\s+/', ' ', trim((string) ($input[$key] ?? ''))) ?? '';
            if (mb_strlen($value) > $max) {
                $errors[$key] = "Use at most {$max} characters.";
            }

            return $value === '' ? null : $value;
        };

        $data = [
            'admission_number' => $text('admission_number', 40),
            'first_name' => $text('first_name', 100),
            'last_name' => $text('last_name', 100),
            'other_names' => $text('other_names', 100),
            'gender' => null,
            'date_of_birth' => null,
            'admitted_on' => CarbonImmutable::today(),
        ];

        foreach (['first_name', 'last_name'] as $required) {
            if ($data[$required] === null) {
                $errors[$required] = 'This is required.';
            }
        }

        if (($input['gender'] ?? '') !== '') {
            $data['gender'] = Gender::tryFrom((string) $input['gender']);
            if ($data['gender'] === null) {
                $errors['gender'] = 'Choose female or male.';
            }
        }

        foreach (['date_of_birth', 'admitted_on'] as $field) {
            if (($input[$field] ?? '') === '') {
                continue;
            }
            $date = self::date((string) $input[$field]);
            if ($date === null) {
                $errors[$field] = 'Enter a real date (YYYY-MM-DD).';
            } else {
                $data[$field] = $date;
            }
        }

        if ($data['date_of_birth'] !== null && $data['date_of_birth']->isFuture()) {
            $errors['date_of_birth'] = 'The date of birth cannot be in the future.';
        }

        if ($data['admission_number'] !== null
            && Student::withTrashed()->where('admission_number', $data['admission_number'])->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $errors['admission_number'] = 'Another student has this admission number.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }
}
