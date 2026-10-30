<?php

/*
| Helpers shared by the fee test files (loaded with require_once, so declared once).
*/

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Fees\CreateFeeItem;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;

/**
 * Greenhill (Primary) on 28 Oct 2026 with this year's terms and classes, and three fee items:
 * Tuition, PTA dues and an optional Bus. Sets them on the running test.
 */
function setUpFeeSchool(): void
{
    $test = test();
    $test->travelTo('2026-10-28 09:00:00');

    $test->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($test->school);
    app(SeedDefaultRoles::class)->handle($test->school);
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    [$test->term1, $test->term2] = Term::orderBy('starts_on')->take(2)->get()->all();
    $test->p1 = SchoolClass::where('name', 'Primary 1')->sole();
    $test->p2 = SchoolClass::where('name', 'Primary 2')->sole();

    $test->tuition = app(CreateFeeItem::class)->handle(['name' => 'Tuition', 'category' => 'tuition']);
    $test->pta = app(CreateFeeItem::class)->handle(['name' => 'PTA dues', 'category' => 'levy']);
    $test->bus = app(CreateFeeItem::class)->handle(['name' => 'Bus', 'category' => 'transport', 'is_optional' => true]);
}

function staffWith(RoleKey $role): User
{
    $user = User::factory()->for(app(CurrentSchool::class)->require())->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

function feeStudent(string $first = 'Ama'): Student
{
    $stream = SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();

    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Mensah', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}
