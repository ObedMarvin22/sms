<?php

namespace App\Actions\Schools;

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\ReservedSubdomains;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates a school (trial), its default roles and its first administrator, then emails
 * the administrator an invitation to set a password.
 */
class CreateSchool
{
    public function __construct(
        private SeedDefaultRoles $seedDefaultRoles,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  list<string>  $levels  SchoolLevel values
     *
     * @throws ValidationException
     */
    public function handle(string $name, string $subdomain, array $levels, string $adminName, string $adminEmail): School
    {
        $data = Validator::make(
            [
                'name' => trim($name),
                'subdomain' => strtolower(trim($subdomain)),
                'levels' => $levels,
                'admin_name' => trim($adminName),
                'admin_email' => strtolower(trim($adminEmail)),
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                // 3–63 characters, lowercase letters, digits and inner hyphens (a DNS label).
                'subdomain' => [
                    'required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/',
                    Rule::notIn(ReservedSubdomains::ALL),
                    Rule::unique('schools', 'subdomain'),
                ],
                'levels' => ['required', 'array', 'min:1'],
                'levels.*' => ['distinct', Rule::enum(SchoolLevel::class)],
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255'],
            ],
            [
                'subdomain.regex' => 'Use 3–63 lowercase letters, numbers or hyphens (not at the start or end).',
                'subdomain.not_in' => 'This subdomain is reserved.',
            ],
        )->validate();

        [$school, $admin] = DB::transaction(function () use ($data): array {
            $school = School::create([
                'name' => $data['name'],
                'subdomain' => $data['subdomain'],
                'status' => SchoolStatus::Trial,
                'levels_offered' => array_map(SchoolLevel::from(...), $data['levels']),
                'settings' => [],
            ]);

            $this->seedDefaultRoles->handle($school);

            $admin = $this->currentSchool->runFor($school, function (School $school) use ($data): User {
                // Unusable password until the invitation is accepted.
                $admin = new User([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => Str::random(64),
                ]);
                $admin->school_id = $school->id;
                $admin->save();
                $admin->assignRole(RoleKey::Administrator->value);

                return $admin;
            });

            return [$school, $admin];
        });

        // After commit: never email an invitation for a school that was rolled back.
        $admin->notify(new SchoolInvitation($school));

        return $school;
    }
}
