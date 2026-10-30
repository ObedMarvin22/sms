<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Academic\DeleteAcademicYear;
use App\Actions\Academic\SetCurrentTerm;
use App\Actions\Classes\AddStream;
use App\Actions\Classes\DeleteClass;
use App\Actions\Classes\DeleteStream;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\MoveStudent;
use App\Actions\Students\PromoteStudents;
use App\Actions\Students\WithdrawStudent;
use App\Actions\Subjects\CreateProgramme;
use App\Enums\AcademicPeriodType;
use App\Enums\EnrolmentStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\Students\Promotion;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Jhs, SchoolLevel::Shs)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);

    $this->year = makeAcademicYear(2026);
    app(GenerateDefaultClasses::class)->handle();
});

function makeAcademicYear(int $start): AcademicYear
{
    $calendar = DefaultCalendar::for($start, AcademicPeriodType::Term);

    return app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
}

function streamOf(string $class, string $stream = 'A'): Stream
{
    return SchoolClass::where('name', $class)->sole()->streams()->where('name', $stream)->sole();
}

function admitInto(string $class, array $input = []): Student
{
    return app(AdmitStudent::class)->handle(array_merge([
        'first_name' => 'Kofi', 'last_name' => 'Asante', 'stream_id' => streamOf($class)->id, 'admitted_on' => '2026-09-08',
    ], $input));
}

/**
 * @return list<string> "JHS 1A:promoted" etc., oldest first
 */
function history(Student $student): array
{
    return Enrolment::with('stream.schoolClass')->where('student_id', $student->id)->orderBy('starts_on')->orderBy('id')->get()
        ->map(fn (Enrolment $e) => $e->stream->full_name.':'.$e->status->value)->all();
}

it('enrols a student into the current year on admission', function () {
    $student = admitInto('JHS 1');

    $enrolment = $student->currentEnrolment()->sole();
    expect($enrolment->academic_year_id)->toBe($this->year->id)
        ->and($enrolment->stream->full_name)->toBe('JHS 1A')
        ->and($enrolment->starts_on->toDateString())->toBe('2026-09-08')
        ->and($enrolment->status)->toBe(EnrolmentStatus::Active);
});

it('needs a stream, a programme for SHS, and a date inside the current year', function () {
    expect(fn () => admitInto('JHS 1', ['stream_id' => '']))->toThrow(ValidationException::class, 'class and stream');
    expect(fn () => admitInto('SHS 1'))->toThrow(ValidationException::class, 'programme');
    expect(fn () => admitInto('JHS 1', ['admitted_on' => '2028-01-01']))->toThrow(ValidationException::class, 'within');

    $programme = app(CreateProgramme::class)->handle('General Science');
    expect(admitInto('SHS 1', ['programme_id' => $programme->id])->currentEnrolment->programme_id)->toBe($programme->id);
});

it('lets the database refuse a second current enrolment', function () {
    $student = admitInto('JHS 1');

    $second = new Enrolment(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'stream_id' => streamOf('JHS 2')->id, 'starts_on' => '2026-10-01']);
    $second->is_current = true;

    expect(fn () => $second->save())->toThrow(QueryException::class);
});

it('moves a student to another stream and keeps the history', function () {
    app(AddStream::class)->handle(SchoolClass::where('name', 'JHS 1')->sole(), 'B');
    $student = admitInto('JHS 1');

    app(MoveStudent::class)->handle($student, streamOf('JHS 1', 'B'), null, '2026-10-01', 'Parent request');

    expect(history($student))->toBe(['JHS 1A:moved', 'JHS 1B:active'])
        ->and($student->fresh()->currentEnrolment->starts_on->toDateString())->toBe('2026-10-01');
    expect(fn () => app(MoveStudent::class)->handle($student->fresh(), streamOf('JHS 1', 'B')))->toThrow(ValidationException::class, 'already');
});

it('promotes, repeats and graduates, keeping every year', function () {
    $next = makeAcademicYear(2027);
    $promoted = admitInto('JHS 1', ['first_name' => 'Ama']);
    $repeater = admitInto('JHS 1', ['first_name' => 'Yaw']);
    $leaver = admitInto('JHS 3', ['first_name' => 'Esi']);

    app(PromoteStudents::class)->handle(streamOf('JHS 1'), $next, [
        $promoted->id => ['action' => 'promote', 'stream_id' => streamOf('JHS 2')->id],
        $repeater->id => ['action' => 'repeat', 'stream_id' => streamOf('JHS 1')->id],
    ]);
    app(PromoteStudents::class)->handle(streamOf('JHS 3'), $next, [
        $leaver->id => ['action' => 'graduate'],
    ]);

    expect(history($promoted))->toBe(['JHS 1A:promoted', 'JHS 2A:active'])
        ->and(history($repeater))->toBe(['JHS 1A:repeated', 'JHS 1A:active'])
        ->and(history($leaver))->toBe(['JHS 3A:graduated'])
        ->and($leaver->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and($promoted->fresh()->currentEnrolment->academic_year_id)->toBe($next->id)
        ->and($promoted->fresh()->currentEnrolment->starts_on->toDateString())->toBe($next->starts_on->toDateString());
});

it('refuses impossible promotions', function () {
    $student = admitInto('JHS 1');
    $promote = app(PromoteStudents::class);

    expect(fn () => $promote->handle(streamOf('JHS 1'), $this->year, [$student->id => ['action' => 'promote', 'stream_id' => streamOf('JHS 2')->id]]))
        ->toThrow(ValidationException::class, 'starts after');

    $next = makeAcademicYear(2027);
    expect(fn () => $promote->handle(streamOf('JHS 1'), $next, [$student->id => ['action' => 'repeat', 'stream_id' => streamOf('JHS 2')->id]]))
        ->toThrow(ValidationException::class, 'repeats');
    expect(fn () => $promote->handle(streamOf('JHS 2'), $next, [$student->id => ['action' => 'promote', 'stream_id' => streamOf('JHS 3')->id]]))
        ->toThrow(ValidationException::class, 'not currently in');

    expect(history($student))->toBe(['JHS 1A:active']);
});

it('withdraws or transfers a student out, keeping the record', function (StudentStatus $leaving, string $enrolmentStatus) {
    $student = admitInto('JHS 2');

    app(WithdrawStudent::class)->handle($student, $leaving, '2026-11-15', 'Family moved');

    expect($student->fresh()->status)->toBe($leaving)
        ->and($student->fresh()->currentEnrolment)->toBeNull()
        ->and(history($student))->toBe(["JHS 2A:{$enrolmentStatus}"]);

    expect(fn () => app(WithdrawStudent::class)->handle($student->fresh(), StudentStatus::Withdrawn))->toThrow(ValidationException::class, 'not currently');
})->with([
    [StudentStatus::Withdrawn, 'withdrawn'],
    [StudentStatus::Transferred, 'transferred_out'],
    [StudentStatus::Graduated, 'graduated'],
]);

it('refuses an end date before the enrolment started', function () {
    $student = admitInto('JHS 2', ['admitted_on' => '2026-10-01']);

    app(WithdrawStudent::class)->handle($student, StudentStatus::Withdrawn, '2026-09-01');
})->throws(ValidationException::class, 'before the enrolment started');

it('will not delete classes, streams or years that have enrolments', function () {
    $student = admitInto('JHS 1');
    app(AddStream::class)->handle(SchoolClass::where('name', 'JHS 1')->sole(), 'B');
    $next = makeAcademicYear(2027);

    expect(fn () => app(DeleteClass::class)->handle(SchoolClass::where('name', 'JHS 1')->sole()))->toThrow(ValidationException::class, 'enrolments');
    expect(fn () => app(DeleteStream::class)->handle(streamOf('JHS 1')))->toThrow(ValidationException::class, 'enrolments');

    app(PromoteStudents::class)->handle(streamOf('JHS 1'), $next, [$student->id => ['action' => 'promote', 'stream_id' => streamOf('JHS 2')->id]]);
    app(SetCurrentTerm::class)->handle($next->terms()->firstOrFail());
    expect(fn () => app(DeleteAcademicYear::class)->handle($this->year->fresh()))->toThrow(ValidationException::class, 'enrolments');

    // Unused ones still go.
    app(DeleteStream::class)->handle(streamOf('JHS 1', 'B'));
    app(DeleteClass::class)->handle(SchoolClass::where('name', 'SHS 3')->sole());
    expect(SchoolClass::where('name', 'SHS 3')->exists())->toBeFalse();
});

describe('screens', function () {
    beforeEach(function () {
        app(SeedDefaultRoles::class)->handle($this->school);
        $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
        $this->admin->assignRole(RoleKey::Administrator->value);
        $this->teacher = User::factory()->for($this->school)->create();
        $this->teacher->assignRole(RoleKey::ClassTeacher->value);
        $this->onSchool($this->school);
    });

    it('shows the current class in the list and the history on the student page', function () {
        $student = admitInto('JHS 1');
        app(AddStream::class)->handle(SchoolClass::where('name', 'JHS 1')->sole(), 'B');
        app(MoveStudent::class)->handle($student, streamOf('JHS 1', 'B'), null, '2026-10-01');

        $this->actingAs($this->teacher)->get('http://greenhill.localhost/students')->assertOk()->assertSee('JHS 1B');
        $this->actingAs($this->teacher)->get("http://greenhill.localhost/students/{$student->id}")
            ->assertOk()->assertSee('Enrolment history')->assertSee('Moved to another stream')
            ->assertDontSee('Leaving the school'); // teachers cannot withdraw
    });

    it("returns 404 for another school's student", function () {
        $other = School::factory()->active()->create();
        // Created as the other school (writing into it from Greenhill is refused by BelongsToSchool).
        $theirs = app(CurrentSchool::class)->runFor($other, fn () => Student::factory()->for($other)->create());

        $this->actingAs($this->admin)->get("http://greenhill.localhost/students/{$theirs->id}")->assertNotFound();
    });

    it('refuses teachers the promotion screen', function () {
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/students/promotion')->assertForbidden();
    });

    it('promotes a stream from the promotion screen with sensible defaults', function () {
        $next = makeAcademicYear(2027);
        $student = admitInto('JHS 1');

        Livewire::actingAs($this->admin)->test(Promotion::class)
            ->set('nextYearId', (string) $next->id)
            ->set('fromStreamId', (string) streamOf('JHS 1')->id)
            ->assertSet("decisions.{$student->id}.stream_id", (string) streamOf('JHS 2')->id)
            ->call('promote')
            ->assertHasNoErrors();

        expect($student->fresh()->currentEnrolment->stream->full_name)->toBe('JHS 2A');
    });

    it('defaults the last class to graduation', function () {
        makeAcademicYear(2027);
        $student = admitInto('SHS 3', ['programme_id' => app(CreateProgramme::class)->handle('General Arts')->id]);

        Livewire::actingAs($this->admin)->test(Promotion::class)
            ->set('fromStreamId', (string) streamOf('SHS 3')->id)
            ->assertSet("decisions.{$student->id}.action", 'graduate');
    });
});
