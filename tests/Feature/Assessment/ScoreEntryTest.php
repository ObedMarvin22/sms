<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\BuildScoreSheet;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\DeleteAssessmentTask;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UpdateAssessmentTask;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\MoveStudent;
use App\Actions\Students\WithdrawStudent;
use App\Actions\Subjects\GenerateDefaultProgrammes;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\ScoreStatus;
use App\Enums\StudentStatus;
use App\Livewire\Assessment\ClassTasks;
use App\Livewire\Assessment\MarkSheet;
use App\Livewire\Assessment\MyClasses;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AssessmentScore;
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
use App\Support\Assessment\ScoreSheet;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function sheetStream(string $class): Stream
{
    return SchoolClass::where('name', $class)->sole()->streams()->orderBy('name')->firstOrFail();
}

function sheetStaff(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function sheetLearner(string $first, Stream $stream, ?int $programmeId = null): Student
{
    return app(AdmitStudent::class)->handle(array_filter([
        'first_name' => $first, 'last_name' => 'Boateng', 'stream_id' => $stream->id,
        'admitted_on' => '2026-09-08', 'programme_id' => $programmeId,
    ]));
}

/**
 * @param  array<string, mixed>  $data
 */
function sheetTask(User $by, Stream $stream, Subject $subject, string $part, array $data = []): AssessmentTask
{
    $component = TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components->firstWhere('name', $part);

    return app(CreateAssessmentTask::class)->handle($by, Term::current()->sole(), $stream, $subject, $component, [
        'name' => 'Task '.fake()->unique()->numberBetween(1, 9999), 'given_on' => '2026-10-20', 'max_mark' => 20, ...$data,
    ]);
}

function enterMark(User $by, AssessmentTask $task, Student $student, string $input): ?AssessmentScore
{
    return app(SaveScore::class)->handle($by, $task, $student, $input);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary, SchoolLevel::Shs)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultProgrammes::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->p4 = sheetStream('Primary 4');
    $this->maths = Subject::where('name', 'Mathematics')->sole();
    [$this->teacher, $staff] = sheetStaff(RoleKey::SubjectTeacher);
    app(AssignTeacher::class)->handle($this->p4, $this->maths, $staff);

    $this->ama = sheetLearner('Ama', $this->p4);
    $this->kofi = sheetLearner('Kofi', $this->p4);
    $this->test = sheetTask($this->teacher, $this->p4, $this->maths, 'Class tests', ['name' => 'Test 1', 'max_mark' => 20]);
});

describe('entering a mark', function () {
    it('stores marks, halves, absent and exempt, and clears a cell', function (string $input, ?ScoreStatus $status, ?string $mark, string $shown) {
        $score = enterMark($this->teacher, $this->test, $this->ama, $input);

        expect($score?->status)->toBe($status)
            ->and($score?->mark)->toBe($mark)
            ->and($score?->display() ?? '')->toBe($shown);
    })->with([
        'a mark' => ['15', ScoreStatus::Marked, '15.0', '15'],
        'a half mark, with a comma' => [' 12,5 ', ScoreStatus::Marked, '12.5', '12.5'],
        'zero' => ['0', ScoreStatus::Marked, '0.0', '0'],
        'full marks' => ['20', ScoreStatus::Marked, '20.0', '20'],
        'absent' => ['a', ScoreStatus::Absent, null, 'A'],
        'exempt' => ['X', ScoreStatus::Exempt, null, 'X'],
        'blank' => ['', null, null, ''],
    ]);

    it('refuses marks that cannot be right', function (string $input, string $message) {
        expect(fn () => enterMark($this->teacher, $this->test, $this->ama, $input))->toThrow(ValidationException::class, $message);
    })->with([
        'over the maximum' => ['21', 'The task is out of 20.'],
        'a tenth' => ['12.3', 'Marks go in whole or half marks'],
        'negative' => ['-1', 'Type a mark'],
        'words' => ['twelve', 'Type a mark'],
    ]);

    it('changes and clears a mark, logging both but not the first entry', function () {
        $score = enterMark($this->teacher, $this->test, $this->ama, '15');
        expect(Activity::where('subject_type', $score->getMorphClass())->count())->toBe(0);

        enterMark($this->teacher, $this->test, $this->ama, '15'); // unchanged: nothing written
        enterMark($this->teacher, $this->test, $this->ama, 'A');
        enterMark($this->teacher, $this->test, $this->ama, '');

        $events = Activity::where('subject_type', $score->getMorphClass())->where('subject_id', $score->id)->orderBy('id')->pluck('event')->all();
        expect($events)->toBe(['updated', 'deleted'])
            ->and(AssessmentScore::count())->toBe(0);
    });

    it('lets only the subject teacher or a head enter marks', function () {
        [$other] = sheetStaff(RoleKey::SubjectTeacher);
        expect(fn () => enterMark($other, $this->test, $this->ama, '10'))->toThrow(AuthorizationException::class);

        [$head] = sheetStaff(RoleKey::Headteacher);
        expect(enterMark($head, $this->test, $this->ama, '10')->entered_by)->toBe($head->id);
    });

    it('refuses a student who is not in the class', function () {
        $stranger = sheetLearner('Yaw', sheetStream('Primary 5'));

        enterMark($this->teacher, $this->test, $stranger, '10');
    })->throws(ValidationException::class, "is not on this class's list");
});

describe('who is on the sheet', function () {
    it('keeps a student who moved class during the term, read-only, and adds them to the new class', function () {
        enterMark($this->teacher, $this->test, $this->kofi, '14');
        app(MoveStudent::class)->handle($this->kofi, sheetStream('Primary 5'), on: '2026-10-25');

        $rows = collect(app(BuildScoreSheet::class)->handle(Term::current()->sole(), $this->p4, $this->maths)->rows);
        $kofi = $rows->first(fn ($r) => $r->student->is($this->kofi));

        expect($kofi->editable)->toBeFalse()
            ->and($kofi->note)->toBe('Moved to another class 25 Oct')
            ->and(fn () => enterMark($this->teacher, $this->test, $this->kofi, '15'))->toThrow(ValidationException::class, 'so their marks cannot change here')
            ->and(AssessmentScore::sole()->mark)->toBe('14.0');

        $p5 = collect(app(BuildScoreSheet::class)->handle(Term::current()->sole(), sheetStream('Primary 5'), $this->maths)->rows);
        expect($p5->first(fn ($r) => $r->student->is($this->kofi))->editable)->toBeTrue();
    });

    it('shows a student who left as "Left" with their marks kept', function () {
        enterMark($this->teacher, $this->test, $this->ama, '18');
        app(WithdrawStudent::class)->handle($this->ama->fresh(), StudentStatus::Withdrawn, '2026-10-26');

        $row = collect(app(BuildScoreSheet::class)->handle(Term::current()->sole(), $this->p4, $this->maths)->rows)
            ->first(fn ($r) => $r->student->is($this->ama));

        expect($row->note)->toBe('Left 26 Oct')->and($row->editable)->toBeFalse();
    });

    it('lists only the programmes that take an SHS elective', function () {
        $shs = sheetStream('SHS 1');
        $science = Programme::where('name', 'General Science')->sole();
        $business = Programme::where('name', 'Business')->sole();
        $kwame = sheetLearner('Kwame', $shs, $science->id);
        $efua = sheetLearner('Efua', $shs, $business->id);
        $names = fn (string $subject) => collect(app(BuildScoreSheet::class)->handle(Term::current()->sole(), $shs, Subject::where('name', $subject)->sole())->rows)
            ->map(fn ($r) => $r->student->first_name)->all();

        expect($names('Physics'))->toBe(['Kwame'])
            ->and($names('Economics'))->toBe(['Efua'])
            ->and($names('English Language'))->toBe(['Efua', 'Kwame']);
    });
});

describe('the scores', function () {
    beforeEach(function () {
        $this->test2 = sheetTask($this->teacher, $this->p4, $this->maths, 'Class tests', ['name' => 'Test 2', 'max_mark' => 50]);
        $this->exercise = sheetTask($this->teacher, $this->p4, $this->maths, 'Class exercises', ['name' => 'Ex 1', 'max_mark' => 10]);
        $this->exam = sheetTask($this->teacher, $this->p4, $this->maths, 'End of term exam', ['name' => 'Exam', 'max_mark' => 100]);
    });

    function sheetResults(Stream $stream, Subject $subject, Student $student): array
    {
        $sheet = app(BuildScoreSheet::class)->handle(Term::current()->sole(), $stream, $subject);
        $names = $sheet->scheme->components->pluck('name', 'id');

        return [
            'parts' => collect($sheet->results[$student->id]['parts'])->mapWithKeys(fn ($v, $id) => [$names[$id] => $v])->all(),
            'total' => $sheet->results[$student->id]['total'],
        ];
    }

    it('adds up the marks inside a part, weighted', function () {
        // Class tests (10%): 15/20 + 40/50 = 55/70 → 7.857… → 7.86
        enterMark($this->teacher, $this->test, $this->ama, '15');
        enterMark($this->teacher, $this->test2, $this->ama, '40');
        // Class exercises (10%): 7.5/10 → 7.5
        enterMark($this->teacher, $this->exercise, $this->ama, '7.5');
        // Exam (70%): 64/100 → 44.8
        enterMark($this->teacher, $this->exam, $this->ama, '64');

        expect(sheetResults($this->p4, $this->maths, $this->ama))->toBe([
            'parts' => ['Class exercises' => 7.5, 'Class tests' => 7.86, 'Project' => null, 'End of term exam' => 44.8],
            'total' => 60.16,
        ]);
    });

    it('counts absent as zero and leaves exempt and empty boxes out', function () {
        enterMark($this->teacher, $this->test, $this->kofi, 'A');    // 0 / 20 counted
        enterMark($this->teacher, $this->test2, $this->kofi, '45');  // 45 / 50
        enterMark($this->teacher, $this->exercise, $this->kofi, 'X'); // left out: part has nothing

        $result = sheetResults($this->p4, $this->maths, $this->kofi);

        expect($result['parts']['Class tests'])->toBe(6.43) // 45/70 × 10
            ->and($result['parts']['Class exercises'])->toBeNull()
            ->and($result['parts']['End of term exam'])->toBeNull()
            ->and($result['total'])->toBe(6.43);
    });

    it('has no total before any mark is in', function () {
        expect(sheetResults($this->p4, $this->maths, $this->ama)['total'])->toBeNull();
    });
});

describe('protecting marks', function () {
    beforeEach(fn () => enterMark($this->teacher, $this->test, $this->ama, '18'));

    it('will not lower "out of" below a mark already given', function () {
        $part = $this->test->component;
        $update = fn (int $max) => app(UpdateAssessmentTask::class)->handle($this->teacher, $this->test, $part, ['name' => 'Test 1', 'given_on' => '2026-10-20', 'max_mark' => $max]);

        expect(fn () => $update(15))->toThrow(ValidationException::class, 'A student already has 18 on this task');

        $update(18);
        expect($this->test->fresh()->max_mark)->toBe(18);
    });

    it('will not delete a task that has marks', function () {
        app(DeleteAssessmentTask::class)->handle($this->teacher, $this->test);
    })->throws(ValidationException::class, 'Test 1 has marks.');
});

describe('the mark sheet', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('saves a typed mark and shows it the way it was stored', function () {
        $key = ScoreSheet::key($this->test->id, $this->ama->id);

        Livewire::actingAs($this->teacher)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertSee('Boateng, Ama')->assertSee('Test 1')
            ->set("cells.$key", '12,5')
            ->assertSet("cells.$key", '12.5')
            ->assertHasNoErrors()
            ->set("cells.$key", '25')
            ->assertHasErrors("cells.$key")
            ->assertSee('Ama Boateng, Test 1: The task is out of 20.')
            ->set("cells.$key", 'a')
            ->assertSet("cells.$key", 'A')
            ->assertHasNoErrors();

        expect(AssessmentScore::sole()->status)->toBe(ScoreStatus::Absent);
    });

    it('gives every cell a key, so the browser receives an object and empty cells can sync', function () {
        enterMark($this->teacher, $this->test, $this->ama, '15');

        $cells = Livewire::actingAs($this->teacher)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths])->get('cells');

        expect($cells)->toBe([
            ScoreSheet::key($this->test->id, $this->ama->id) => '15',
            ScoreSheet::key($this->test->id, $this->kofi->id) => '',
        ]);
    });

    it('shows the class teacher every subject of their class, to look at only', function () {
        [$classTeacher, $staff] = sheetStaff(RoleKey::ClassTeacher);
        app(AssignClassTeacher::class)->handle($this->p4, $staff);
        enterMark($this->teacher, $this->test, $this->ama, '15');

        Livewire::actingAs($classTeacher)->test(MyClasses::class)
            ->assertSee('Mathematics')->assertSee('English Language')->assertSee('View only');

        $sheet = Livewire::actingAs($classTeacher)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertSee('Only the subject teacher or the head can change it')
            ->assertSeeHtml('disabled');

        $sheet->set('cells.'.ScoreSheet::key($this->test->id, $this->ama->id), '1')->assertForbidden();
        expect(AssessmentScore::sole()->mark)->toBe('15.0');

        Livewire::actingAs($classTeacher)->test(ClassTasks::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertDontSeeHtml('data-test="add-task-');
    });

    it('refuses everyone else', function () {
        [$other] = sheetStaff(RoleKey::SubjectTeacher);

        $this->actingAs($other)->get(route('assessment.marks', [$this->p4, $this->maths]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('assessment.marks', [$this->p4, $this->maths]))->assertOk()->assertSee('Each mark saves when you leave the box');
    });

    it('asks for tasks before marks', function () {
        $english = Subject::where('name', 'English Language')->sole();
        [$head] = sheetStaff(RoleKey::Headteacher);

        Livewire::actingAs($head)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $english])
            ->assertSee('No tasks yet');
    });

    it('builds the sheet in a fixed number of queries, whatever the class size', function () {
        foreach (range(1, 30) as $i) {
            $student = sheetLearner("Pupil{$i}", $this->p4);
            enterMark($this->teacher, $this->test, $student, (string) ($i % 20));
        }

        DB::enableQueryLog();
        app(BuildScoreSheet::class)->handle(Term::current()->sole(), $this->p4, $this->maths);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBeLessThanOrEqual(8);
    });
});
