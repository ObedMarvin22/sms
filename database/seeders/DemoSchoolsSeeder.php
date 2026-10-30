<?php

namespace Database\Seeders;

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Subjects\GenerateDefaultProgrammes;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data: Greenhill and Hillview, each set up for this school year with an
 * administrator, a class teacher (Primary 4A) and about 600 students with guardians
 * (DemoStudentsSeeder), plus an Orange Tree console super admin. All demo accounts use the
 * password below.
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
                $teacher = $this->staff($school, "teacher@{$subdomain}.test", $spec['teacher'], RoleKey::ClassTeacher);

                $this->setUpSchool($school);
                // The demo teacher can take Primary 4A's register and see its class list.
                $stream = SchoolClass::where('name', 'Primary 4')->sole()->streams()->orderBy('name')->firstOrFail();
                if (! ClassTeacherAssignment::where('stream_id', $stream->id)->exists()) {
                    app(AssignClassTeacher::class)->handle($stream, $teacher);
                }

                $this->call(DemoStudentsSeeder::class);
            });
        }

        $this->consoleAdmin();
    }

    /**
     * A login and the matching staff record (class teachers are assigned through Staff).
     */
    private function staff(School $school, string $email, string $name, RoleKey $role): Staff
    {
        $user = User::where('school_id', $school->id)->where('email', $email)->first()
            ?? new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);

        $user->school_id = $school->id;
        $user->email_verified_at ??= now();
        $user->save();

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }

        [$first, $last] = explode(' ', $name, 2);
        $staff = Staff::where('email', $email)->first() ?? new Staff(['first_name' => $first, 'last_name' => $last, 'email' => $email]);
        $staff->forceFill(['user_id' => $user->id])->save();

        return $staff;
    }

    /**
     * What the setup wizard does: this school year (first term current), default classes,
     * subjects, and SHS programmes where the school has SHS.
     */
    private function setUpSchool(School $school): void
    {
        if (AcademicYear::exists()) {
            return;
        }

        $startYear = now()->month >= 9 ? now()->year : now()->year - 1;
        $calendar = DefaultCalendar::for($startYear, AcademicPeriodType::Term);
        app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
        app(GenerateDefaultClasses::class)->handle();

        if ($school->levels_offered->contains(SchoolLevel::Shs)) {
            app(GenerateDefaultProgrammes::class)->handle(); // adds the subjects too
        } else {
            app(GenerateDefaultSubjects::class)->handle();
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
