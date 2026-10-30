<?php

namespace App\Actions\Subjects;

use App\Enums\SchoolLevel;
use App\Models\Programme;
use App\Models\Subject;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Checks shared by the subject and programme Actions. Queries are school-scoped.
 */
final class SubjectRules
{
    public static function name(string $name, ?Subject $ignore = null): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Enter a subject name of up to 100 characters.']);
        }
        if (Subject::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'There is already a subject with this name.']);
        }

        return $name;
    }

    public static function code(?string $code, ?Subject $ignore = null): ?string
    {
        $code = mb_strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }
        if (preg_match('/^[A-Z0-9-]{1,10}$/', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'Use up to 10 letters, numbers or hyphens.']);
        }
        if (Subject::where('code', $code)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            throw ValidationException::withMessages(['code' => 'Another subject uses this code.']);
        }

        return $code;
    }

    /**
     * @param  list<string>  $levels
     * @return list<SchoolLevel>
     */
    public static function levels(array $levels): array
    {
        $offered = app(CurrentSchool::class)->require()->levels_offered;
        $parsed = [];

        foreach (array_unique($levels) as $value) {
            $level = SchoolLevel::tryFrom($value);

            if ($level === null || ! $offered->contains($level)) {
                throw ValidationException::withMessages(['levels' => 'Choose levels the school offers.']);
            }
            $parsed[] = $level;
        }

        if ($parsed === []) {
            throw ValidationException::withMessages(['levels' => 'Choose at least one level.']);
        }

        return $parsed;
    }

    public static function programmeName(string $name, ?Programme $ignore = null): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['programme' => 'Enter a programme name of up to 100 characters.']);
        }
        if (Programme::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            throw ValidationException::withMessages(['programme' => 'There is already a programme with this name.']);
        }

        return $name;
    }

    public static function requireShs(): void
    {
        if (! app(CurrentSchool::class)->require()->levels_offered->contains(SchoolLevel::Shs)) {
            throw ValidationException::withMessages(['programme' => 'Programmes are for schools that offer SHS.']);
        }
    }
}
