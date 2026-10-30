<?php

namespace Database\Seeders;

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data: Greenhill and Hillview, each with an administrator and a class teacher,
 * plus an Orange Tree console super admin. All demo accounts use the password below.
 * Credentials are listed in docs/DEVELOPMENT.md. Safe to run again (no duplicates).
 */
class DemoSchoolsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const CONSOLE_ADMIN_EMAIL = 'admin@orangetree.test';

    /**
     * @var array<string, array{name: string, colour: string, levels: list<SchoolLevel>, admin: string, teacher: string}>
     */
    private const SCHOOLS = [
        'greenhill' => [
            'name' => 'Greenhill Academy',
            'colour' => '#0A7D3C',
            'levels' => [SchoolLevel::Creche, SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs],
            'admin' => 'Ama Mensah',
            'teacher' => 'Kwame Owusu',
        ],
        'hillview' => [
            'name' => 'Hillview School',
            'colour' => '#1E3A8A',
            'levels' => [SchoolLevel::Primary, SchoolLevel::Jhs, SchoolLevel::Shs],
            'admin' => 'Efua Asante',
            'teacher' => 'Yaw Boateng',
        ],
    ];

    public function run(SeedDefaultRoles $seedDefaultRoles, CurrentSchool $currentSchool): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSchoolsSeeder must never run in production.');
        }

        foreach (self::SCHOOLS as $subdomain => $spec) {
            $school = School::firstOrCreate(['subdomain' => $subdomain], [
                'name' => $spec['name'],
                'primary_colour' => $spec['colour'],
                'status' => SchoolStatus::Active,
                'levels_offered' => $spec['levels'],
                'settings' => [],
            ]);

            $seedDefaultRoles->handle($school);

            $currentSchool->runFor($school, function (School $school) use ($subdomain, $spec): void {
                $this->staff($school, "admin@{$subdomain}.test", $spec['admin'], RoleKey::Administrator);
                $this->staff($school, "teacher@{$subdomain}.test", $spec['teacher'], RoleKey::ClassTeacher);
            });
        }

        $this->consoleAdmin();
    }

    private function staff(School $school, string $email, string $name, RoleKey $role): void
    {
        $user = User::where('school_id', $school->id)->where('email', $email)->first()
            ?? new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);

        $user->school_id = $school->id;
        $user->email_verified_at ??= now();
        $user->save();

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }
    }

    private function consoleAdmin(): void
    {
        $user = User::whereNull('school_id')->where('email', self::CONSOLE_ADMIN_EMAIL)->first()
            ?? new User(['name' => 'Orange Tree Admin', 'email' => self::CONSOLE_ADMIN_EMAIL, 'password' => self::PASSWORD]);

        $user->school_id = null;
        $user->platform_role = PlatformRoleKey::SuperAdmin;
        $user->email_verified_at ??= now();
        $user->save();
    }
}
