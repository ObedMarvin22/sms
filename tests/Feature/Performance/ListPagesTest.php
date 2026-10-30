<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Attendance/helpers.php';

/*
| N+1 guard: each list page must run the same number of queries for 10 students as for 20.
| A query per row (a missing eager load) makes the count grow with the data and fails here.
*/

beforeEach(function () {
    $this->travelTo('2026-10-28 09:00:00');

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    $this->streams = Stream::query()->orderBy('id')->take(2)->get();
    $this->p1 = SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();
    $this->teacher = classTeacherOf($this->p1);
    $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->admin->assignRole(RoleKey::Administrator->value);
    $this->added = 0;
    $this->onSchool($this->school);
});

/**
 * Adds students (alternating between two streams), each with a guardian, then takes today's
 * registers for those streams.
 */
function addStudentsWithGuardians(int $count): void
{
    $test = test();
    for ($i = 0; $i < $count; $i++) {
        $n = ++$test->added;
        $student = pupil("Pupil{$n}", $test->streams[$n % 2]);
        app(AddGuardian::class)->handle($student, ['name' => "Parent {$n} Mensah", 'phone' => '0244'.str_pad((string) $n, 6, '0', STR_PAD_LEFT), 'relationship' => 'mother']);
    }
    foreach ($test->streams as $stream) {
        register($test->admin, $stream);
    }
}

function queriesFor(User $user, string $url): int
{
    test()->actingAs($user);
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

it('runs a fixed number of queries per page, however many rows it shows', function (string $url, string $who) {
    addStudentsWithGuardians(10);
    $user = $who === 'teacher' ? $this->teacher : $this->admin;
    $url = 'http://greenhill.localhost'.$url;
    queriesFor($user, $url); // warm-up: the first request also fills the permission cache
    $small = queriesFor($user, $url);

    addStudentsWithGuardians(10);

    expect(queriesFor($user, $url))->toBe($small);
})->with([
    'students' => ['/students', 'admin'],
    'student search' => ['/students?search=mensah', 'admin'],
    'student phone search' => ['/students?search=0244', 'admin'],
    'guardians' => ['/guardians', 'admin'],
    'class list' => ['/class-lists', 'teacher'],
    'register' => ['/attendance', 'teacher'],
    "today's attendance" => ['/attendance/today', 'admin'],
]);
