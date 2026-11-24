<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\BuildProgressOverview;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UnlockSheet;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\WithdrawStudent;
use App\Actions\Subjects\GenerateDefaultProgrammes;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SheetProgress;
use App\Enums\StudentStatus;
use App\Livewire\Assessment\Progress;
use App\Models\AcademicYear;
use App\Models\AssessmentScheme;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\Programme;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function progUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->withTwoFactor()->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function progStream(string $class): Stream
{
    return SchoolClass::where('name', $class)->sole()->streams()->firstOrFail();
}

function progPupil(string $first, Stream $stream, ?int $programmeId = null): Student
{
    return app(AdmitStudent::class)->handle(array_filter(['first_name' => $first, 'last_name' => 'Owusu', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08', 'programme_id' => $programmeId]));
}

/**
 * One task in every part of the scheme for this class and subject.
 *
 * @return list<AssessmentTask>
 */
function progTasks(User $by, Stream $stream, Subject $subject): array
{
    $scheme = TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id);

    return array_values($scheme->components->map(fn ($part) => app(CreateAssessmentTask::class)->handle($by, Term::current()->sole(), $stream, $subject, $part, [
        'name' => $part->name.' task', 'given_on' => '2026-10-20', 'max_mark' => 10,
    ]))->all());
}

function progFill(User $by, array $tasks, Student $student, string $mark = '5'): void
{
    foreach ($tasks as $task) {
        app(SaveScore::class)->handle($by, $task, $student, $mark);
    }
}

function progState(Stream $stream, Subject $subject): ?SheetProgress
{
    return app(BuildProgressOverview::class)->handle(Term::current()->sole())->cell($stream->id, $subject->id)?->progress;
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Shs)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultProgrammes::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->p4 = progStream('Primary 4');
    $this->maths = Subject::where('name', 'Mathematics')->sole();
    [$this->teacher, $staff] = progUser(RoleKey::SubjectTeacher);
    app(AssignTeacher::class)->handle($this->p4, $this->maths, $staff);
    [$this->head] = progUser(RoleKey::Headteacher);

    $this->ama = progPupil('Ama', $this->p4);
    $this->kofi = progPupil('Kofi', $this->p4);
});

describe('the states', function () {
    it('moves from not started to in progress, ready, submitted and reopened', function () {
        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::NotStarted);

        $tasks = progTasks($this->teacher, $this->p4, $this->maths);
        progFill($this->teacher, $tasks, $this->ama);
        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::InProgress);

        $cell = app(BuildProgressOverview::class)->handle(Term::current()->sole())->cell($this->p4->id, $this->maths->id);
        expect([$cell->filled, $cell->expected, $cell->percent()])->toBe([4, 8, 50]);

        progFill($this->teacher, $tasks, $this->kofi, 'A');
        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::Ready);

        app(SubmitSheet::class)->handle($this->teacher, Term::current()->sole(), $this->p4, $this->maths);
        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::Submitted);

        app(UnlockSheet::class)->handle($this->head, AssessmentSheet::sole(), 'Correcting an exam mark');
        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::Reopened);
    });

    it('is not ready while a part has no task', function () {
        $tasks = progTasks($this->teacher, $this->p4, $this->maths);
        // Remove the exam task before any marks, then fill the rest.
        $exam = collect($tasks)->first(fn ($t) => $t->name === 'End of term exam task');
        $exam->delete();
        progFill($this->teacher, array_values(array_filter($tasks, fn ($t) => $t->isNot($exam))), $this->ama);
        progFill($this->teacher, array_values(array_filter($tasks, fn ($t) => $t->isNot($exam))), $this->kofi);

        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::InProgress);
    });

    it('does not wait for students who left', function () {
        $tasks = progTasks($this->teacher, $this->p4, $this->maths);
        progFill($this->teacher, $tasks, $this->ama);
        app(WithdrawStudent::class)->handle($this->kofi->fresh(), StudentStatus::Withdrawn, '2026-10-27');

        expect(progState($this->p4, $this->maths))->toBe(SheetProgress::Ready);
    });

    it('counts only the programmes that take an SHS elective', function () {
        $shs = progStream('SHS 1');
        $physics = Subject::where('name', 'Physics')->sole();
        $kwame = progPupil('Kwame', $shs, Programme::where('name', 'General Science')->sole()->id);
        progPupil('Efua', $shs, Programme::where('name', 'Business')->sole()->id); // does not take Physics

        $tasks = progTasks($this->head, $shs, $physics);
        progFill($this->head, $tasks, $kwame);

        expect(progState($shs, $physics))->toBe(SheetProgress::Ready);
    });

    it('lists comment sheets for early years, and notes levels without assessment', function () {
        $overview = app(BuildProgressOverview::class)->handle(Term::current()->sole());
        $kg = collect($overview->levels)->firstWhere(fn ($l) => $l['level'] === SchoolLevel::Kg);

        expect($kg['note'])->toBeNull()
            ->and($kg['commentsOnly'])->toBeTrue()
            ->and($overview->cell(progStream('KG 1')->id, Subject::where('name', 'Numeracy')->sole()->id)?->progress)->toBe(SheetProgress::NotStarted);

        AssessmentScheme::with('components')->where('level', SchoolLevel::Shs)->get()->each(function ($s) {
            $s->components->each->delete();
            $s->delete();
        });
        $notes = collect(app(BuildProgressOverview::class)->handle(Term::current()->sole())->levels)->mapWithKeys(fn ($l) => [$l['level']->value => $l['note']])->all();
        expect($notes['shs'])->toBe('Assessment is not set up for this level yet.');
    });

    it('takes the same number of queries however big the school is', function () {
        $count = function (): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            app(BuildProgressOverview::class)->handle(Term::current()->sole());
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        progTasks($this->teacher, $this->p4, $this->maths);
        $small = $count();

        foreach (range(1, 20) as $i) {
            $pupil = progPupil("Pupil{$i}", progStream('Primary 5'));
        }
        $tasks = progTasks($this->head, progStream('Primary 5'), Subject::where('name', 'Science')->sole());
        progFill($this->head, $tasks, $pupil);

        expect($count())->toBe($small)->and($small)->toBeLessThanOrEqual(18);
    });
});

describe('the page', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('is for heads only', function () {
        $this->actingAs($this->teacher)->get(route('assessment.progress'))->assertForbidden();
        $this->actingAs($this->head)->get(route('assessment.progress'))->assertOk()->assertSee('Marks progress')->assertSee('of');
    });

    it('shows the totals, and filters by state, teacher and level', function () {
        $tasks = progTasks($this->teacher, $this->p4, $this->maths);
        progFill($this->teacher, $tasks, $this->ama);
        progFill($this->teacher, $tasks, $this->kofi);
        app(SubmitSheet::class)->handle($this->teacher, Term::current()->sole(), $this->p4, $this->maths);
        $teacherStaffId = (string) Staff::where('user_id', $this->teacher->id)->value('id');

        $page = Livewire::actingAs($this->head)->test(Progress::class)
            ->assertSeeHtml('data-test="submitted-count">1<')
            ->assertSee('Primary')->assertSeeHtml('data-test="level-kg"');

        $page->set('teacher', $teacherStaffId)
            ->assertSee('Primary 4A')
            ->assertDontSee('Primary 1A')
            ->set('state', 'outstanding')
            ->assertSee('No sheets match these filters.')
            ->call('clearFilters')
            ->set('level', 'shs')
            ->assertSeeHtml('data-test="level-shs"')
            ->assertDontSeeHtml('data-test="level-primary"');
    });

    it('can look at another term', function () {
        $second = Term::where('sequence', 2)->sole();

        Livewire::actingAs($this->head)->test(Progress::class)
            ->set('termId', $second->id)
            ->assertSet('termId', $second->id)
            ->assertSeeHtml('data-test="submitted-count">0<');
    });
});
