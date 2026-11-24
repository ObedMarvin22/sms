<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Academic\DeleteAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\DeleteAssessmentTask;
use App\Actions\Assessment\SaveAssessmentScheme;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UpdateAssessmentTask;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\DeleteStream;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Classes\SetClassSubjects;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\AssessmentMode;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Assessment\ClassTasks;
use App\Livewire\Assessment\MyClasses;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\AssessmentTask;
use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function taskStream(string $class): Stream
{
    return SchoolClass::where('name', $class)->sole()->streams()->orderBy('name')->firstOrFail();
}

function taskSubject(string $name): Subject
{
    return Subject::where('name', $name)->sole();
}

function taskPart(Stream $stream, string $name): AssessmentComponent
{
    return TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components->firstWhere('name', $name);
}

function teacherOf(Stream $stream, Subject $subject, RoleKey $role = RoleKey::SubjectTeacher): User
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->create();
    $user->assignRole($role->value);
    app(AssignTeacher::class)->handle($stream, $subject, Staff::factory()->for($school)->create(['user_id' => $user->id]));

    return $user;
}

/**
 * @param  array<string, mixed>  $data
 */
function addTask(User $by, Stream $stream, Subject $subject, string $part, array $data = []): AssessmentTask
{
    return app(CreateAssessmentTask::class)->handle($by, Term::current()->sole(), $stream, $subject, taskPart($stream, $part), [
        'name' => 'Class test 1', 'given_on' => '2026-10-20', 'max_mark' => 20, ...$data,
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->term = Term::current()->sole();
    $this->p4 = taskStream('Primary 4');
    $this->maths = taskSubject('Mathematics');
    $this->teacher = teacherOf($this->p4, $this->maths);
    $this->head = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->head->assignRole(RoleKey::Headteacher->value);
});

describe('who may set tasks', function () {
    it('lets the subject teacher add a task and logs it', function () {
        $task = addTask($this->teacher, $this->p4, $this->maths, 'Class tests');

        expect($task->name)->toBe('Class test 1')
            ->and($task->max_mark)->toBe(20)
            ->and($task->given_on->toDateString())->toBe('2026-10-20')
            ->and($task->created_by)->toBe($this->teacher->id);

        $log = Activity::where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->sole();
        expect($log->log_name)->toBe('grades')->and($log->event)->toBe('created');
    });

    it('refuses the teacher for another subject or another class', function (string $class, string $subject) {
        addTask($this->teacher, taskStream($class), taskSubject($subject), 'Class tests');
    })->throws(AuthorizationException::class)->with([
        'same class, other subject' => ['Primary 4', 'English Language'],
        'same subject, other class' => ['Primary 5', 'Mathematics'],
    ]);

    it('refuses staff without grades permissions', function () {
        $bursar = User::factory()->for($this->school)->create();
        $bursar->assignRole(RoleKey::Bursar->value);

        addTask($bursar, $this->p4, $this->maths, 'Class tests');
    })->throws(AuthorizationException::class);

    it('lets a head set tasks for any subject the class takes, but not one it does not take', function () {
        expect(addTask($this->head, taskStream('JHS 1'), taskSubject('Social Studies'), 'Class tests')->exists)->toBeTrue();

        expect(fn () => addTask($this->head, $this->p4, taskSubject('Social Studies'), 'Class tests'))
            ->toThrow(AuthorizationException::class);
    });

    it('keeps the tasks when the teacher changes', function () {
        $task = addTask($this->teacher, $this->p4, $this->maths, 'Class tests');
        $next = teacherOf($this->p4, $this->maths);

        app(UpdateAssessmentTask::class)->handle($next, $task, taskPart($this->p4, 'Class tests'), ['name' => 'Test 1', 'given_on' => '2026-10-20', 'max_mark' => 25]);

        expect($task->fresh()->max_mark)->toBe(25)
            ->and(fn () => app(DeleteAssessmentTask::class)->handle($this->teacher, $task))->toThrow(AuthorizationException::class);
    });
});

describe('what a task must be', function () {
    it('refuses bad details', function (array $data, string $field) {
        try {
            addTask($this->teacher, $this->p4, $this->maths, 'Class tests', $data);
            $this->fail('No error.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }
    })->with([
        'blank name' => [['name' => ' '], 'name'],
        'date before the term' => [['given_on' => '2026-08-01'], 'given_on'],
        'date after the term' => [['given_on' => '2027-01-20'], 'given_on'],
        'not a date' => [['given_on' => '2026-02-31'], 'given_on'],
        'out of 0' => [['max_mark' => 0], 'max_mark'],
        'out of 1001' => [['max_mark' => 1001], 'max_mark'],
        'out of 12.5' => [['max_mark' => '12.5'], 'max_mark'],
    ]);

    it('refuses the same name twice under one part, but allows it under another', function () {
        addTask($this->teacher, $this->p4, $this->maths, 'Class tests');
        addTask($this->teacher, $this->p4, $this->maths, 'Class exercises');

        expect(fn () => addTask($this->teacher, $this->p4, $this->maths, 'Class tests', ['name' => 'class TEST 1']))
            ->toThrow(ValidationException::class, 'There is already a task called class TEST 1 under Class tests.');
    });

    it('allows one exam task per term', function () {
        $exam = addTask($this->teacher, $this->p4, $this->maths, 'End of term exam', ['name' => 'Exam', 'max_mark' => 100]);

        expect(fn () => addTask($this->teacher, $this->p4, $this->maths, 'End of term exam', ['name' => 'Paper 2']))
            ->toThrow(ValidationException::class, 'End of term exam has one task per term.');

        // Editing the exam itself is fine.
        app(UpdateAssessmentTask::class)->handle($this->teacher, $exam, taskPart($this->p4, 'End of term exam'), ['name' => 'Exam', 'given_on' => '2026-12-10', 'max_mark' => 80]);
        expect($exam->fresh()->max_mark)->toBe(80);
    });

    it('refuses a part from another level', function () {
        app(CreateAssessmentTask::class)->handle($this->teacher, $this->term, $this->p4, $this->maths, taskPart(taskStream('JHS 1'), 'Class tests'), ['name' => 'T', 'given_on' => '2026-10-20', 'max_mark' => 10]);
    })->throws(ValidationException::class, "Choose a part of Primary's assessment for this year.");

    it('has nothing to add for a comments-only level', function () {
        $kg = taskStream('KG 1');
        $numeracy = taskSubject('Numeracy');
        teacherOf($kg, $numeracy);
        app(SaveAssessmentScheme::class)->handle(AcademicYear::current()->sole(), SchoolLevel::Kg, AssessmentMode::Weighted, [['name' => 'Exam', 'kind' => 'exam', 'weight' => 100]]);
        $part = taskPart($kg, 'Exam');
        app(SaveAssessmentScheme::class)->handle(AcademicYear::current()->sole(), SchoolLevel::Kg, AssessmentMode::Descriptive);

        expect(AssessmentComponent::find($part->id))->toBeNull();
    });
});

describe('protecting tasks', function () {
    beforeEach(function () {
        $this->task = addTask($this->teacher, $this->p4, $this->maths, 'Class tests');
    });

    it('will not remove a scheme part that has tasks, or switch it to comments only', function (AssessmentMode $mode) {
        $parts = $mode === AssessmentMode::Weighted ? [['name' => 'Exam', 'kind' => 'exam', 'weight' => 100]] : [];

        app(SaveAssessmentScheme::class)->handle(AcademicYear::current()->sole(), SchoolLevel::Primary, $mode, $parts);
    })->throws(ValidationException::class, 'Class tests already has tasks, so it cannot be removed.')
        ->with([AssessmentMode::Weighted, AssessmentMode::Descriptive]);

    it('will not take a subject with tasks off the class', function () {
        $class = $this->p4->schoolClass;
        $others = ClassSubject::where('school_class_id', $class->id)->where('subject_id', '!=', $this->maths->id)->pluck('subject_id')->all();

        expect(fn () => app(SetClassSubjects::class)->handle($class, $others))
            ->toThrow(ValidationException::class, 'Mathematics has assessment tasks in Primary 4, so it cannot be removed.');

        app(SetClassSubjects::class)->handle($class, [...$others, $this->maths->id]); // unchanged is fine
        expect(ClassSubject::where('school_class_id', $class->id)->count())->toBe(count($others) + 1);
    });

    it('will not delete a subject, stream or year that has tasks', function () {
        expect(fn () => app(DeleteSubject::class)->handle($this->maths))->toThrow(ValidationException::class, 'Mathematics has assessment tasks')
            ->and(fn () => app(DeleteStream::class)->handle($this->p4))->toThrow(ValidationException::class)
            ->and(fn () => app(DeleteAcademicYear::class)->handle(AcademicYear::current()->sole()))->toThrow(ValidationException::class);
    });

    it('deletes a task and logs it', function () {
        app(DeleteAssessmentTask::class)->handle($this->teacher, $this->task);

        expect(AssessmentTask::count())->toBe(0)
            ->and(Activity::where('subject_id', $this->task->id)->where('subject_type', $this->task->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
    });
});

describe('the screens', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('lists only the teacher\'s own classes, with task counts', function () {
        addTask($this->teacher, $this->p4, $this->maths, 'Class tests');

        Livewire::actingAs($this->teacher)->test(MyClasses::class)
            ->assertSee('Primary 4')->assertSee('Mathematics')->assertSee('1 task')
            ->assertDontSee('English Language');
    });

    it('shows heads every class with its teacher, and filters', function () {
        Livewire::actingAs($this->head)->test(MyClasses::class)
            ->assertSee('JHS 3')->assertSee('No teacher assigned')->assertSee(Staff::where('user_id', $this->teacher->id)->sole()->full_name)
            ->set('search', 'social')
            ->assertSee('Social Studies')->assertDontSee('Numeracy');
    });

    it('tells a teacher with no assignments what to do', function () {
        $new = User::factory()->for($this->school)->create();
        $new->assignRole(RoleKey::SubjectTeacher->value);

        Livewire::actingAs($new)->test(MyClasses::class)->assertSee('No subjects to teach yet');
    });

    it('refuses other classes and staff without grades permissions', function () {
        $this->actingAs($this->teacher)->get(route('assessment.tasks', [taskStream('Primary 5'), $this->maths]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('assessment.tasks', [$this->p4, $this->maths]))->assertOk()->assertSee('Mathematics, Primary 4');

        $bursar = User::factory()->for($this->school)->withTwoFactor()->create();
        $bursar->assignRole(RoleKey::Bursar->value);
        $this->actingAs($bursar)->get(route('assessment.index'))->assertForbidden();
    });

    it('adds, edits and deletes a task from the class page', function () {
        $part = taskPart($this->p4, 'Class tests');

        $page = Livewire::actingAs($this->teacher)->test(ClassTasks::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->call('startCreate', $part->id)
            ->assertSet('givenOn', '2026-10-28')
            ->set('name', 'Fractions quiz')->set('maxMark', '0')
            ->call('save')->assertHasErrors('max_mark')
            ->set('maxMark', '15')
            ->call('save')->assertHasNoErrors()
            ->assertSee('Fractions quiz')->assertSee('out of 15');

        $task = AssessmentTask::sole();
        $page->call('startEdit', $task->id)->assertSet('maxMark', '15')
            ->set('maxMark', '20')->call('save')->assertHasNoErrors()->assertSee('out of 20')
            ->call('delete', $task->id)->assertDontSee('Fractions quiz');

        expect(AssessmentTask::count())->toBe(0);
    });

    it('hides "Add task" on the exam once it has one', function () {
        $exam = taskPart($this->p4, 'End of term exam');
        addTask($this->teacher, $this->p4, $this->maths, 'End of term exam', ['name' => 'Exam']);

        Livewire::actingAs($this->teacher)->test(ClassTasks::class, ['stream' => $this->p4, 'subject' => $this->maths])
            ->assertDontSeeHtml('data-test="add-task-'.$exam->id.'"')
            ->assertSeeHtml('data-test="add-task-'.taskPart($this->p4, 'Project')->id.'"');
    });

    it('explains comments-only levels and missing schemes', function () {
        $kg = taskStream('KG 1');
        $numeracy = taskSubject('Numeracy');

        Livewire::actingAs($this->head)->test(ClassTasks::class, ['stream' => $kg, 'subject' => $numeracy])
            ->assertSee('KG is assessed with comments only');

        $jhs = AssessmentScheme::with('components')->where('level', SchoolLevel::Jhs)->sole();
        $jhs->components->each->delete();
        $jhs->delete();

        Livewire::actingAs($this->head)->test(ClassTasks::class, ['stream' => taskStream('JHS 1'), 'subject' => taskSubject('Social Studies')])
            ->assertSee('JHS assessment is not set up yet');
    });
});

it('keeps Marks highlighted in the sidebar on a class page', function () {
    $this->onSchool($this->school);

    $html = $this->actingAs($this->teacher)->get(route('assessment.tasks', [$this->p4, $this->maths]))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('assessment.index').'" data-current="data-current"');
});
