<?php

namespace App\Actions\Guardians;

use App\Enums\GuardianAccess;
use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

/**
 * Validation shared by the guardian Actions. Queries are school-scoped (BelongsToSchool).
 */
final class GuardianRules
{
    /**
     * A guardian's own details. Phones come back as +233XXXXXXXXX.
     *
     * @param  array<string, mixed>  $input
     * @return array{name: string, phone: string, alt_phone: ?string, email: ?string, occupation: ?string, address: ?string}
     */
    public static function details(array $input, ?Guardian $ignore = null): array
    {
        $errors = [];
        $text = function (string $key, int $max) use ($input, &$errors): ?string {
            $value = preg_replace('/\s+/', ' ', trim((string) ($input[$key] ?? ''))) ?? '';
            if (mb_strlen($value) > $max) {
                $errors[$key] = "Use at most {$max} characters.";
            }

            return $value === '' ? null : $value;
        };

        $name = $text('name', 150);
        $email = $text('email', 255);
        $occupation = $text('occupation', 100);
        $address = $text('address', 255);

        if ($name === null) {
            $errors['name'] = 'This is required.';
        }

        $phone = Phone::normalise((string) ($input['phone'] ?? ''));
        if ($phone === null) {
            $errors['phone'] = 'Enter a Ghana phone number, e.g. 024 412 3456.';
        }

        $altPhone = null;
        if (trim((string) ($input['alt_phone'] ?? '')) !== '') {
            $altPhone = Phone::normalise((string) $input['alt_phone']);
            if ($altPhone === null) {
                $errors['alt_phone'] = 'Enter a Ghana phone number, e.g. 024 412 3456.';
            }
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($phone !== null) {
            $existing = Guardian::withTrashed()->where('phone', $phone)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->first();
            if ($existing !== null) {
                $errors['phone'] = "{$existing->name} already uses this number. Link them instead.";
            }
        }

        // A null name or phone always has an error; checking them here also tells PHPStan.
        if ($errors !== [] || $name === null || $phone === null) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'alt_phone' => $altPhone,
            'email' => $email === null ? null : mb_strtolower($email),
            'occupation' => $occupation,
            'address' => $address,
        ];
    }

    /**
     * How a guardian relates to one child.
     *
     * @param  array<string, mixed>  $input
     * @return array{relationship: GuardianRelationship, access_level: GuardianAccess, can_pick_up: bool}
     */
    public static function link(array $input): array
    {
        $errors = [];

        $relationship = GuardianRelationship::tryFrom((string) ($input['relationship'] ?? ''));
        if ($relationship === null) {
            $errors['relationship'] = 'Choose how they are related to the child.';
        }

        $access = GuardianAccess::tryFrom((string) ($input['access_level'] ?? GuardianAccess::Full->value));
        if ($access === null) {
            $errors['access_level'] = 'Choose an access level.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'relationship' => $relationship,
            'access_level' => $access,
            'can_pick_up' => filter_var($input['can_pick_up'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
