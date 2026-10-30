<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\SetAttendanceLockTime;
use App\Actions\Attendance\TakeAttendance;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\MoveStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\AttendanceStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Attendance\Register;
use App\Livewire\Attendance\Today;
use App\Livewire\School\Profile;
use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00'); // a Wednesday in the first term, before the 10:00 lock

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    $this->p1 = SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();
    $this->p2 = SchoolClass::where('name', 'Primary 2')->sole()->streams()->sole();

    $this->teacher = classTeacherOf($this->p1);
    $this->otherTeacher = classTeacherOf($this->p2);
    $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->admin->assignRole(RoleKey::Administrator->value);

    $this->kofi = pupil('Kofi', $this->p1);
    $this->ama = pupil('Ama', $this->p1);
});

function classTeacherOf(Stream $stream): User
{
    $user = User::factory()->for(app(CurrentSchool::class)->require())->create();
    $user->assignRole(RoleKey::ClassTeacher->value);
    $staff = Staff::factory()->for(app(CurrentSchool::class)->require())->create(['user_id' => $user->id]);
    app(AssignClassTeacher::class)->handle($stream, $staff);

    return $user;
}

function pupil(string $firstName, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $firstName, 'last_name' => 'Mensah', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

/**
 * @param  array<int, string>  $statuses  student id => status; everyone else present
 */
function register(User $user, Stream $stream, array $statuses = [], string $date = '2026-10-28', ?string $reason = null): AttendanceSession
{
    $marks = [];
    foreach (AttendanceRules::enrolmentsOn($stream, CarbonImmutable::parse($date)) as $enrolment) {
        $marks[$enrolment->student_id] = ['status' => $statuses[$enrolment->student_id] ?? 'present', 'note' => ''];
    }

    return app(TakeAttendance::class)->handle($user, $stream, $date, $marks, $reason);
}

function markOf(Student $student, string $date = '2026-10-28'): ?AttendanceStatus
{
    return AttendanceRecord::where('student_id', $student->id)
        ->whereHas('session', fn ($q) => $q->whereDate('date', $date))->first()?->status;
}

it('lets the class teacher take their register, linked to each enrolment', function () {
    $session = register($this->teacher, $this->p1, [$this->ama->id => 'absent']);

    expect($session->taken_by)->toBe($this->teacher->id)
        ->and(markOf($this->kofi))->toBe(AttendanceStatus::Present)
        ->and(markOf($this->ama))->toBe(AttendanceStatus::Absent)
        ->and(AttendanceRecord::where('student_id', $this->ama->id)->sole()->enrolment_id)->toBe($this->ama->currentEnrolment->id);
});

it('refuses a teacher another stream\'s register', function () {
    expect(fn () => register($this->otherTeacher, $this->p1))->toThrow(AuthorizationException::class);

    expect(AttendanceRules::streamsFor($this->otherTeacher)->pluck('id')->all())->toBe([$this->p2->id]);
});

it('gives a teacher who is not a class teacher no registers', function () {
    $user = User::factory()->for($this->school)->create();
    $user->assignRole(RoleKey::ClassTeacher->value);

    expect(AttendanceRules::streamsFor($user))->toHaveCount(0)
        ->and(fn () => register($user, $this->p1))->toThrow(AuthorizationException::class);
});

it('lets administrators take any register', function () {
    register($this->admin, $this->p2);

    expect(AttendanceRules::streamsFor($this->admin)->count())->toBe(Stream::count());
});

it('refuses days without school', function (string $date, string $message) {
    expect(fn () => register($this->teacher, $this->p1, [], $date))->toThrow(ValidationException::class, $message);
})->with([
    'future' => ['2026-10-29', 'future day'],
    'weekend' => ['2026-10-24', 'weekends'],
    'before the term' => ['2026-09-07', 'must fall within'],
    'not a date' => ['2026-02-30', 'real date'],
]);

it('requires a mark for every student on the register', function () {
    expect(fn () => app(TakeAttendance::class)->handle($this->teacher, $this->p1, '2026-10-28', [
        $this->kofi->id => ['status' => 'present'],
        $this->ama->id => ['status' => 'asleep'],
    ]))->toThrow(ValidationException::class, 'Mark Ama Mensah present, late, absent or excused');

    $stranger = pupil('Yaw', $this->p2);
    expect(fn () => app(TakeAttendance::class)->handle($this->teacher, $this->p1, '2026-10-28', [
        $this->kofi->id => ['status' => 'present'], $this->ama->id => ['status' => 'present'], $stranger->id => ['status' => 'present'],
    ]))->toThrow(ValidationException::class, 'not in this stream');

    expect(AttendanceSession::count())->toBe(0);
});

it('lets the teacher correct the register before the lock, without duplicates or logging', function () {
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
    $this->travelTo('2026-10-28 09:55:00');
    register($this->teacher, $this->p1, [$this->ama->id => 'late']);

    expect(AttendanceSession::count())->toBe(1)
        ->and(AttendanceRecord::count())->toBe(2)
        ->and(markOf($this->ama))->toBe(AttendanceStatus::Late)
        ->and(Activity::where('log_name', 'attendance')->count())->toBe(0);
});

it('stops the teacher after the lock time', function () {
    register($this->teacher, $this->p1);
    $this->travelTo('2026-10-28 10:00:00');

    expect(fn () => register($this->teacher, $this->p1, [$this->ama->id => 'absent']))
        ->toThrow(ValidationException::class, 'locked at 10:00');
    expect(markOf($this->ama))->toBe(AttendanceStatus::Present);
});

it('lets an administrator change a locked register with a reason, and logs each change', function () {
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
    $this->travelTo('2026-10-28 14:00:00');

    expect(fn () => register($this->admin, $this->p1, [$this->ama->id => 'excused']))
        ->toThrow(ValidationException::class, 'Give a reason');
    expect(markOf($this->ama))->toBe(AttendanceStatus::Absent);

    register($this->admin, $this->p1, [$this->ama->id => 'excused'], reason: 'Mother sent a note: hospital visit');
    // Saving the locked register again with no changes needs no reason and logs nothing.
    register($this->admin, $this->p1, [$this->ama->id => 'excused']);

    $log = Activity::where('log_name', 'attendance')->sole();
    expect(markOf($this->ama))->toBe(AttendanceStatus::Excused)
        ->and($log->event)->toBe('edited_after_lock')
        ->and($log->causer_id)->toBe($this->admin->id)
        ->and($log->properties->all())
        ->toMatchArray(['student_id' => $this->ama->id, 'old' => 'absent', 'new' => 'excused', 'reason' => 'Mother sent a note: hospital visit']);
});

it('uses the school\'s own lock time', function () {
    app(SetAttendanceLockTime::class)->handle($this->school, '07:30');
    $this->school->refresh();

    expect(fn () => register($this->teacher, $this->p1))->toThrow(ValidationException::class, 'locked at 07:30');
    expect(fn () => app(SetAttendanceLockTime::class)->handle($this->school, '25:00'))->toThrow(ValidationException::class);
});

it('puts a moved student on the right register for each day', function () {
    app(MoveStudent::class)->handle($this->ama, $this->p2, null, '2026-10-26', 'Parent asked');

    $names = fn (Stream $stream, string $date) => AttendanceRules::enrolmentsOn($stream, CarbonImmutable::parse($date))
        ->map(fn ($e) => $e->student->first_name)->all();

    expect($names($this->p1, '2026-10-23'))->toBe(['Ama', 'Kofi'])
        ->and($names($this->p1, '2026-10-26'))->toBe(['Kofi'])
        ->and($names($this->p2, '2026-10-26'))->toBe(['Ama'])
        ->and($names($this->p2, '2026-10-23'))->toBe([]);
});

describe('screens', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('opens the teacher\'s own stream with everyone present to start', function () {
        Livewire::actingAs($this->teacher)->test(Register::class)
            ->assertSet('streamId', (string) $this->p1->id)
            ->assertSet("marks.{$this->ama->id}.status", 'present')
            ->assertSee('Not taken yet')
            ->set("marks.{$this->ama->id}.status", 'absent')
            ->call('save')
            ->assertHasNoErrors();

        expect(markOf($this->ama))->toBe(AttendanceStatus::Absent);
    });

    it('cannot be pointed at another teacher\'s stream', function () {
        Livewire::withQueryParams(['stream' => $this->p1->id])->actingAs($this->otherTeacher)->test(Register::class)
            ->assertSet('streamId', (string) $this->p2->id);
    });

    it('shows a locked register read-only to the teacher', function () {
        register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
        $this->travelTo('2026-10-28 11:00:00');

        Livewire::actingAs($this->teacher)->test(Register::class)
            ->assertSet("marks.{$this->ama->id}.status", 'absent')
            ->assertSee('This register locked at 10:00')
            ->assertSee('Ask an administrator')
            ->assertDontSee('Save register');
    });

    it('lists today\'s registers for administrators', function () {
        register($this->teacher, $this->p1, [$this->ama->id => 'absent']);

        Livewire::actingAs($this->admin)->test(Today::class)
            ->assertSee('1 of '.Stream::count().' registers taken')
            ->assertSeeInOrder(['Primary 1A', 'Taken 08:00', '1', '1', 'Primary 2A', 'Not taken']);

        $this->actingAs($this->teacher)->get('http://greenhill.localhost/attendance/today')->assertForbidden();
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/attendance')->assertOk()->assertSee('Kofi Mensah');
    });

    it('refuses staff without an attendance permission', function () {
        $gate = User::factory()->for($this->school)->create();
        $gate->assignRole(RoleKey::GateStaff->value);

        $this->actingAs($gate)->get('http://greenhill.localhost/attendance')->assertForbidden();
    });

    it('sets the lock time on the school profile', function () {
        Livewire::actingAs($this->admin)->test(Profile::class)
            ->assertSet('attendance_lock_time', '10:00')
            ->set('attendance_lock_time', '09:15')
            ->call('saveAttendanceLockTime')
            ->assertHasNoErrors();

        expect(AttendanceRules::lockTime($this->school->fresh()))->toBe('09:15');
    });
});
