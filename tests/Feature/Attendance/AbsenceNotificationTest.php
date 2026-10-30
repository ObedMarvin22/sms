<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Attendance\SetAttendanceLockTime;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Sms\SetSmsSettings;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SmsStatus;
use App\Livewire\Attendance\Register;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\MessageCreditEntry;
use App\Models\Notification as NotificationModel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAbsent;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

/**
 * A school with this year's calendar and default classes; returns its Primary 1 stream.
 */
function absenceSchool(string $subdomain): Stream
{
    $school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => $subdomain]);
    app(CurrentSchool::class)->set($school);
    app(SeedDefaultRoles::class)->handle($school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    return SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();
}

function guardianOf(Student $student, string $name, string $phone, string $access = 'full'): Guardian
{
    return app(AddGuardian::class)->handle($student, ['name' => $name, 'phone' => $phone, 'relationship' => 'mother', 'access_level' => $access]);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00'); // Wednesday; registers lock at 10:00

    $this->p1 = absenceSchool('greenhill');
    $this->school = app(CurrentSchool::class)->require();
    $this->teacher = classTeacherOf($this->p1);
    $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->admin->assignRole(RoleKey::Administrator->value);

    $this->kofi = pupil('Kofi', $this->p1);
    $this->ama = pupil('Ama', $this->p1);
    $this->amaMother = guardianOf($this->ama, 'Akosua Mensah', '0244000001');
    $this->amaFather = guardianOf($this->ama, 'Kwame Mensah', '0244000002');
    $this->kofiMother = guardianOf($this->kofi, 'Efua Mensah', '0244000003');
});

it('sends nothing before the lock, so the teacher can still correct the register', function () {
    Notification::fake();

    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
    $this->artisan('attendance:notify-absences')->assertSuccessful();

    Notification::assertNothingSent();
});

it('notifies every guardian of an absent student once the register locks, only once', function () {
    Notification::fake();
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);

    $this->travelTo('2026-10-28 10:00:00');
    $this->artisan('attendance:notify-absences')->expectsOutputToContain('1 absent students')->assertSuccessful();
    $this->artisan('attendance:notify-absences')->assertSuccessful();

    Notification::assertSentToTimes($this->amaMother, StudentAbsent::class, 1);
    Notification::assertSentToTimes($this->amaFather, StudentAbsent::class, 1);
    Notification::assertNotSentTo($this->kofiMother, StudentAbsent::class);
    expect(AttendanceRecord::where('student_id', $this->ama->id)->sole()->absence_notified_at?->toDateTimeString())->toBe('2026-10-28 10:00:00')
        ->and(AttendanceRecord::where('student_id', $this->kofi->id)->sole()->absence_notified_at)->toBeNull();
});

it('does not notify for late or excused students', function () {
    Notification::fake();
    register($this->teacher, $this->p1, [$this->ama->id => 'late', $this->kofi->id => 'excused']);

    $this->travelTo('2026-10-28 10:00:00');
    $this->artisan('attendance:notify-absences');

    Notification::assertNothingSent();
});

it('skips guardians with no app access, and waits for a guardian when a child has none', function () {
    Notification::fake();
    $contactOnly = guardianOf($this->kofi, 'Yaw Mensah', '0244000004', 'none');
    $nia = pupil('Nia', $this->p1);
    register($this->teacher, $this->p1, [$this->kofi->id => 'absent', $nia->id => 'absent']);

    $this->travelTo('2026-10-28 10:00:00');
    $this->artisan('attendance:notify-absences');

    Notification::assertSentTo($this->kofiMother, StudentAbsent::class);
    Notification::assertNotSentTo($contactOnly, StudentAbsent::class);
    expect(AttendanceRecord::where('student_id', $nia->id)->sole()->absence_notified_at)->toBeNull();

    // A guardian added later in the day still hears at the next run.
    $niaMother = guardianOf($nia, 'Abena Mensah', '0244000005');
    $this->artisan('attendance:notify-absences');
    Notification::assertSentToTimes($niaMother, StudentAbsent::class, 1);
    Notification::assertSentToTimes($this->kofiMother, StudentAbsent::class, 1);
});

it('notifies straight away when an administrator marks a student absent after the lock', function () {
    Notification::fake();
    register($this->teacher, $this->p1);

    $this->travelTo('2026-10-28 11:30:00');
    register($this->admin, $this->p1, [$this->ama->id => 'absent'], reason: 'Went home sick');

    Notification::assertSentTo($this->amaMother, StudentAbsent::class);
    Notification::assertNotSentTo($this->kofiMother, StudentAbsent::class);
});

it('never notifies about an earlier day', function () {
    Notification::fake();
    $this->travelTo('2026-10-28 14:00:00');

    register($this->admin, $this->p1, [$this->ama->id => 'absent'], '2026-10-27', 'Register was wrong');
    $this->artisan('attendance:notify-absences');

    Notification::assertNothingSent();
});

it('stores the in-app notice on the guardian in the right school, with no SMS until the school turns it on', function () {
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
    $this->travelTo('2026-10-28 10:00:00');
    $this->artisan('attendance:notify-absences');

    $notice = $this->amaMother->notifications()->sole();
    expect($notice)->toBeInstanceOf(NotificationModel::class)
        ->and($notice->school_id)->toBe($this->school->id)
        ->and($notice->data['student_id'])->toBe($this->ama->id)
        ->and($notice->data['date'])->toBe('2026-10-28')
        ->and($notice->data['message'])->toContain('Ama was marked absent')
        ->and($this->kofiMother->notifications()->count())->toBe(0)
        ->and(SmsMessage::count())->toBe(0);
});

it('also sends the notice by SMS when the school turned absence SMS on', function () {
    app(SetSmsSettings::class)->handle($this->school, ['absence'], '100');
    MessageCreditEntry::create(['type' => 'top_up', 'units' => 50, 'note' => 'Test']);
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);
    $this->travelTo('2026-10-28 10:00:00');

    $this->artisan('attendance:notify-absences');

    expect(SmsMessage::orderBy('to')->pluck('to')->all())->toBe(['+233244000001', '+233244000002'])
        ->and(SmsMessage::pluck('status')->unique()->all())->toBe([SmsStatus::Sent])
        ->and(SmsMessage::first()?->message)->toContain('Ama was marked absent at');
});

it('follows each school\'s own lock time and never crosses schools', function () {
    Notification::fake();
    register($this->teacher, $this->p1, [$this->ama->id => 'absent']);

    $hillviewP1 = absenceSchool('hillview');
    $hillview = app(CurrentSchool::class)->require();
    app(SetAttendanceLockTime::class)->handle($hillview, '12:00');
    $hillviewTeacher = classTeacherOf($hillviewP1);
    $esi = pupil('Esi', $hillviewP1);
    $esiMother = guardianOf($esi, 'Adjoa Owusu', '0244000001'); // same phone, other school
    register($hillviewTeacher, $hillviewP1, [$esi->id => 'absent']);
    app(CurrentSchool::class)->clear();

    $this->travelTo('2026-10-28 10:00:00');
    $this->artisan('attendance:notify-absences');
    Notification::assertSentTo($this->amaMother, StudentAbsent::class);
    Notification::assertNotSentTo($esiMother, StudentAbsent::class);

    $this->travelTo('2026-10-28 12:00:00');
    $this->artisan('attendance:notify-absences');
    Notification::assertSentToTimes($esiMother, StudentAbsent::class, 1);
    Notification::assertSentToTimes($this->amaMother, StudentAbsent::class, 1);
});

it('shows on the register when guardians were notified', function () {
    $this->onSchool($this->school);
    $this->travelTo('2026-10-28 10:15:00');
    register($this->admin, $this->p1, [$this->ama->id => 'absent'], reason: 'Taken late');

    Livewire::withQueryParams(['stream' => $this->p1->id])->actingAs($this->teacher)->test(Register::class)
        ->assertSeeHtml('data-test="absence-notified"')
        ->assertSee('Guardians notified 10:15');
});

it('runs the absence notices every weekday minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'attendance:notify-absences'))
        ->sole();

    expect($event->expression)->toBe('* * * * 1-5')
        ->and($event->withoutOverlapping)->toBeTrue();
});
