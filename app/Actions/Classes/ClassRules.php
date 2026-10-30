<?php

namespace App\Actions\Classes;

use App\Models\SchoolClass;
use App\Models\Stream;
use Illuminate\Validation\ValidationException;

/**
 * Name rules shared by the class and stream Actions. Queries are school-scoped (BelongsToSchool).
 */
final class ClassRules
{
    public static function className(string $name, ?SchoolClass $ignore = null): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '' || mb_strlen($name) > 50) {
            throw ValidationException::withMessages(['name' => 'Enter a class name of up to 50 characters.']);
        }

        $taken = SchoolClass::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'There is already a class with this name.']);
        }

        return $name;
    }

    public static function streamName(SchoolClass $class, string $name, ?Stream $ignore = null): string
    {
        $name = mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)) ?? '');

        if ($name === '' || mb_strlen($name) > 20) {
            throw ValidationException::withMessages(['stream' => 'Enter a stream name of up to 20 characters, e.g. A.']);
        }

        $taken = $class->streams()
            ->where('name', $name)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['stream' => "{$class->name} already has a stream {$name}."]);
        }

        return $name;
    }
}
