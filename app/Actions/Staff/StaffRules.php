<?php

namespace App\Actions\Staff;

use App\Enums\Gender;
use App\Enums\RoleKey;
use App\Models\Staff;
use App\Models\User;
use App\Support\Phone;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Validation shared by the staff Actions. Queries are school-scoped (BelongsToSchool) except
 * users, which are checked against the current school explicitly.
 */
final class StaffRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{staff_number: ?string, first_name: string, last_name: string, other_names: ?string, gender: ?Gender, phone: ?string, email: ?string, job_title: ?string, employed_on: ?CarbonImmutable}
     */
    public static function details(array $input, ?Staff $ignore = null): array
    {
        $text = fn (string $key, int $max) => self::text($input[$key] ?? null, $key, $max);
        $errors = [];

        $data = [
            'staff_number' => $text('staff_number', 30),
            'first_name' => $text('first_name', 100),
            'last_name' => $text('last_name', 100),
            'other_names' => $text('other_names', 100),
            'gender' => null,
            'phone' => null,
            'email' => null,
            'job_title' => $text('job_title', 100),
            'employed_on' => null,
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

        if (($input['phone'] ?? '') !== '') {
            $data['phone'] = Phone::normalise((string) $input['phone']);
            if ($data['phone'] === null) {
                $errors['phone'] = 'Enter a Ghanaian phone number, e.g. 024 412 3456.';
            }
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif (self::emailTaken($email, $ignore)) {
                $errors['email'] = 'Someone at this school already uses this email.';
            }
            $data['email'] = $email;
        }

        if (($input['employed_on'] ?? '') !== '') {
            $value = (string) $input['employed_on'];
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;

            if ($date === null || $date->format('Y-m-d') !== $value) {
                $errors['employed_on'] = 'Enter a real date (YYYY-MM-DD).';
            } else {
                $data['employed_on'] = $date;
            }
        }

        if ($data['staff_number'] !== null
            && Staff::withTrashed()->where('staff_number', $data['staff_number'])->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $errors['staff_number'] = 'Another staff member has this number.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * To give or remove a role, the actor must hold its sensitive permissions (money, approvals,
     * admin powers) themselves. Everyday teaching permissions do not need this (Obed's choice), so
     * administrators can set up teachers but cannot make anyone bursar. Never the Parent role.
     *
     * @param  list<RoleKey>  $roles
     */
    public static function assertCanChangeRoles(User $actor, array $roles): void
    {
        foreach ($roles as $role) {
            if ($role === RoleKey::Parent) {
                throw ValidationException::withMessages(['roles' => 'Parents are added as guardians, not as staff.']);
            }

            foreach ($role->defaultPermissions() as $permission) {
                if ($permission->isSensitive() && ! $actor->can($permission->value)) {
                    throw ValidationException::withMessages([
                        'roles' => "You cannot give or remove the {$role->label()} role: it has permissions you do not have.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $values
     * @return list<RoleKey>
     */
    public static function roles(array $values): array
    {
        $roles = [];
        foreach (array_unique($values) as $value) {
            $roles[] = RoleKey::tryFrom($value) ?? throw ValidationException::withMessages(['roles' => 'Unknown role.']);
        }

        return $roles;
    }

    private static function emailTaken(string $email, ?Staff $ignore): bool
    {
        $schoolId = app(CurrentSchool::class)->require()->id;

        $userTaken = User::where('school_id', $schoolId)->where('email', $email)
            ->when($ignore?->user_id, fn ($q, $id) => $q->whereKeyNot($id))->exists();
        $staffTaken = Staff::where('email', $email)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists();

        return $userTaken || $staffTaken;
    }

    private static function text(mixed $value, string $key, int $max): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$key => "Use at most {$max} characters."]);
        }

        return $value === '' ? null : $value;
    }
}
