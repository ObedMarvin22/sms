<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\DeleteAssessmentTask;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UnlockSheet;
use App\Actions\Assessment\UpdateAssessmentTask;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\WithdrawStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SheetStatus;
use App\Enums\StudentStatus;
use App\Livewire\Assessment\ClassTasks;
use App\Livewire\Assessment\MarkSheet;
use App\Livewire\Assessment\MyClasses;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AssessmentScore;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Assessment\ScoreSheet;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function lockUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function lockPupil(string $first, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Asante', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

function lockTask(User $by, Stream $stream, Subject $subject, string $part, string $name, int $max = 20): AssessmentTask
{
    $component = TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components->firstWhere('name', $part);

    return app(CreateAssessmentTask::class)->handle($by, Term::current()->sole(), $stream, $subject, $component, ['name' => $name, 'given_on' => '2026-10-20', 'max_mark' => $max]);
}

function lockMark(User $by, AssessmentTask $task, Student $student, string $input): ?AssessmentScore
{
    return app(SaveScore::class)->handle($by, $task, $student, $input);
}

function lockSubmit(User $by, Stream $stream, Subject $subject): AssessmentSheet
{
    return app(SubmitSheet::class)->handle($by, Term::current()->sole(), $stream, $subject);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->p4 = SchoolClass::where('name', 'Primary 4')->sole()->streams()->firstOrFail();
    $this->maths = Subject::where('name', 'Mathematics')->sole();
    [$this->teacher, $staff] = lockUser(RoleKey::SubjectTeacher);
    app(AssignTeacher::class)->handle($this->p4, $this->maths, $staff);
    [$this->head] = lockUser(RoleKey::Headteacher);

    $this->ama = lockPupil('Ama', $this->p4);
    $this->kofi = lockPupil('Kofi', $this->p4);

    // One task per part, all marked except Kofi's exam.
    $this->tasks = collect(['Class exercises' => 'Ex 1', 'Class tests' => 'Test 1', 'Project' => 'Project', 'End of term exam' => 'Exam'])
        ->map(fn (string $name, string $part) => lockTask($this->teacher, $this->p4, $this->maths, $part, $name, $part === 'End of term exam' ? 100 : 20));
    foreach ($this->tasks as $task) {
        lockMark($this->teacher, $task, $this->ama, '10');
        if ($task->name !== 'Exam') {
            lockMark($this->teacher, $task, $this->kofi, 'A');
        }
    }
    $this->exam = $this->tasks['End of term exam'];
});

describe('submitting', function () {
    it('says exactly what is missing', function () {
        try {
            lockSubmit($this->teacher, $this->p4, $this->maths);
            $this->fail('Submitted an incomplete sheet.');
        } catch (ValidationException $e) {
            expect($e->errors()['sheet'])->toBe(['Kofi Asante has no mark for Exam.']);
        }

        expect(AssessmentSheet::count())->toBe(0);
    });

    it('lists every part without a task', function () {
        $english = Subject::where('name', 'English Language')->sole();

        try {
            lockSubmit($this->head, $this->p4, $english);
            $this->fail('Submitted a sheet with no tasks.');
        } catch (ValidationException $e) {
            expect($e->errors()['sheet'])->toBe([
                'Class exercises has no task yet.', 'Class tests has no task yet.', 'Project has no task yet.', 'End of term exam has no task yet.',
            ]);
        }
    });

    it('locks a complete sheet; students who left do not hold it up', function () {
        $yaw = lockPupil('Yaw', $this->p4);
        app(WithdrawStudent::class)->handle($yaw->fresh(), StudentStatus::Withdrawn, '2026-10-27');
        lockMark($this->teacher, $this->exam, $this->kofi, 'X');

        $sheet = lockSubmit($this->teacher, $this->p4, $this->maths);

        expect($sheet->status)->toBe(SheetStatus::Submitted)
            ->and($sheet->submitted_by)->toBe($this->teacher->id)
            ->and(Activity::where('subject_type', $sheet->getMorphClass())->where('subject_id', $sheet->id)->where('event', 'created')->exists())->toBeTrue();
    });

    it('lets only the subject teacher or a head submit', function () {
        lockMark($this->teacher, $this->exam, $this->kofi, '50');
        [$other] = lockUser(RoleKey::SubjectTeacher);

        expect(fn () => lockSubmit($other, $this->p4, $this->maths))->toThrow(AuthorizationException::class)
            ->and(lockSubmit($this->head, $this->p4, $this->maths)->submitted_by)->toBe($this->head->id);
    });
});

describe('a submitted sheet', function () {
    beforeEach(function () {
        lockMark($this->teacher, $this->exam, $this->kofi, '50');
        $this->sheet = lockSubmit($this->teacher, $this->p4, $this->maths);
    });

    it('refuses marks from everyone, heads included', function (string $who) {
        lockMark($this->{$who}, $this->exam, $this->kofi, '60');
    })->throws(ValidationException::class, 'These marks were submitted on 28 Oct and are locked.')->with(['teacher', 'head']);

    it('refuses task changes', function () {
        $exam = $this->exam->fresh();

        expect(fn () => lockTask($this->teacher, $this->p4, $this->maths, 'Class tests', 'Test 2'))->toThrow(ValidationException::class, 'are locked')
            ->and(fn () => app(UpdateAssessmentTask::class)->handle($this->teacher, $exam, $exam->component, ['name' => 'Final', 'given_on' => '2026-12-10', 'max_mark' => 100]))->toThrow(ValidationException::class, 'are locked')
            ->and(fn () => app(DeleteAssessmentTask::class)->handle($this->teacher, $exam))->toThrow(ValidationException::class, 'are locked');
    });

    it('cannot be submitted twice', function () {
        lockSubmit($this->teacher, $this->p4, $this->maths);
    })->throws(ValidationException::class, 'already submitted');

    it('is unlocked only by a head, with a reason', function () {
        expect(fn () => app(UnlockSheet::class)->handle($this->teacher, $this->sheet, 'Wrong exam mark for Kofi'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(UnlockSheet::class)->handle($this->head, $this->sheet, ' oops '))->toThrow(ValidationException::class, 'Say why');

        $sheet = app(UnlockSheet::class)->handle($this->head, $this->sheet, 'Wrong exam mark for Kofi');

        expect($sheet->status)->toBe(SheetStatus::Reopened)
            ->and($sheet->unlocked_by)->toBe($this->head->id)
            ->and($sheet->unlock_reason)->toBe('Wrong exam mark for Kofi');

        $log = Activity::where('subject_type', $sheet->getMorphClass())->where('subject_id', $sheet->id)->where('event', 'updated')->latest('id')->first();
        expect($log->attribute_changes['attributes'])->toMatchArray(['status' => 'reopened', 'unlock_reason' => 'Wrong exam mark for Kofi']);
    });

    it('after an unlock, logs every save, first entries too, and can be submitted again', function () {
        app(UnlockSheet::class)->handle($this->head, $this->sheet, 'Wrong exam mark for Kofi');

        lockMark($this->teacher, $this->exam, $this->kofi, '65');
        $late = lockPupil('Esi', $this->p4); // joined late: her first marks are logged now
        foreach ($this->tasks as $task) {
            lockMark($this->teacher, $task, $late, '5');
        }

        $kofi = AssessmentScore::where(['assessment_task_id' => $this->exam->id, 'student_id' => $this->kofi->id])->sole();
        $esi = AssessmentScore::where(['assessment_task_id' => $this->exam->id, 'student_id' => $late->id])->sole();
        expect(Activity::where('subject_type', $kofi->getMorphClass())->where('subject_id', $kofi->id)->where('event', 'updated')->exists())->toBeTrue()
            ->and(Activity::where('subject_type', $esi->getMorphClass())->where('subject_id', $esi->id)->where('event', 'created')->sole()->causer_id)->toBe($this->teacher->id);

        expect(lockSubmit($this->teacher, $this->p4, $this->maths)->status)->toBe(SheetStatus::Submitted)
            ->and(AssessmentSheet::count())->toBe(1);
    });
});

describe('the screens', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('shows what is missing and keeps Submit off until the sheet is complete', function () {
        $page = Livewire::actingAs($this->teacher)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertSee('Not submitted yet')
            ->assertSee('Kofi Asante has no mark for Exam.')
            ->assertSeeHtml('data-test="submit-sheet"');

        expect($page->html())->toMatch('/data-test="submit-sheet"[^>]*disabled|disabled[^>]*data-test="submit-sheet"/');

        $page->set('cells.'.ScoreSheet::key($this->exam->id, $this->kofi->id), '70')
            ->assertSee('Every box is filled')
            ->call('submitSheet')
            ->assertSee('Submitted and locked')
            ->assertDontSeeHtml('data-test="submit-sheet"');

        expect(AssessmentSheet::sole()->status)->toBe(SheetStatus::Submitted);
    });

    it('locks the boxes and task buttons, and lets the head unlock with a reason', function () {
        lockMark($this->teacher, $this->exam, $this->kofi, '50');
        lockSubmit($this->teacher, $this->p4, $this->maths);

        Livewire::actingAs($this->teacher)->test(ClassTasks::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertSee('Submitted and locked')
            ->assertDontSeeHtml('data-test="add-task-')
            ->assertDontSeeHtml('data-test="unlock"');

        Livewire::actingAs($this->head)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertSeeHtml('data-test="unlock"')
            ->set('unlockReason', 'short')
            ->call('unlockSheet')
            ->assertHasErrors('unlock_reason')
            ->set('unlockReason', 'Exam marks were entered for the wrong paper')
            ->call('unlockSheet')
            ->assertHasNoErrors()
            ->assertSee('Unlocked for corrections')
            ->assertSee('Exam marks were entered for the wrong paper')
            ->assertSee('Submit again');
    });

    it('shows the sheet state on the Marks list', function () {
        lockMark($this->teacher, $this->exam, $this->kofi, '50');
        lockSubmit($this->teacher, $this->p4, $this->maths);

        Livewire::actingAs($this->teacher)->test(MyClasses::class)
            ->assertSeeHtml('data-test="sheet-badge"')->assertSee('Submitted');
    });
});
