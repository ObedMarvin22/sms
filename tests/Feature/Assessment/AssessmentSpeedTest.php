<?php

/*
| Sprint 5 step 7: the assessment screens take the same number of queries however big the class
| or the term's marks get. Each test measures a small and a large case and expects them equal.
*/

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\SaveComment;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\TaskRules;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\ComponentKind;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Assessment\CommentSheet;
use App\Livewire\Assessment\MarkSheet;
use App\Livewire\Assessment\MyClasses;
use App\Models\AcademicYear;
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
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function speedQueries(Closure $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function speedPupils(Stream $stream, int $count, string $prefix): array
{
    return array_map(fn (int $i) => app(AdmitStudent::class)->handle([
        'first_name' => "{$prefix}{$i}", 'last_name' => 'Kyei', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08',
    ]), range(1, $count));
}

/**
 * @param  list<Student>  $students
 */
function speedMarks(User $by, Stream $stream, Subject $subject, array $students): void
{
    $scheme = TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id);
    $tasks = AssessmentTask::where(['stream_id' => $stream->id, 'subject_id' => $subject->id])->get();
    if ($tasks->isEmpty()) {
        foreach ($scheme->components as $part) {
            foreach (range(1, $part->kind === ComponentKind::Exam ? 1 : 2) as $n) {
                $tasks->push(app(CreateAssessmentTask::class)->handle($by, Term::current()->sole(), $stream, $subject, $part, [
                    'name' => "{$part->name} {$n}", 'given_on' => '2026-10-20', 'max_mark' => 20,
                ]));
            }
        }
    }

    foreach ($students as $student) {
        foreach ($tasks as $task) {
            app(SaveScore::class)->handle($by, $task, $student, (string) random_int(0, 20));
        }
    }
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->user = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->user->assignRole(RoleKey::ClassTeacher->value);
    $staff = Staff::factory()->for($this->school)->create(['user_id' => $this->user->id]);
    $this->p4 = SchoolClass::where('name', 'Primary 4')->sole()->streams()->firstOrFail();
    $this->kg1 = SchoolClass::where('name', 'KG 1')->sole()->streams()->firstOrFail();
    $this->maths = Subject::where('name', 'Mathematics')->sole();
    app(AssignTeacher::class)->handle($this->p4, $this->maths, $staff);
    app(AssignClassTeacher::class)->handle($this->kg1, $staff);

    $this->onSchool($this->school);
});

it('opens a mark sheet in the same number of queries for 5 or 45 students with a full term of marks', function () {
    $open = fn () => speedQueries(fn () => Livewire::actingAs($this->user)->test(MarkSheet::class, ['stream' => $this->p4, 'subject' => $this->maths]));

    speedMarks($this->user, $this->p4, $this->maths, speedPupils($this->p4, 5, 'Small'));
    $small = $open();

    speedMarks($this->user, $this->p4, $this->maths, speedPupils($this->p4, 40, 'Large'));
    $large = $open();

    expect($large)->toBe($small)->and($small)->toBeLessThanOrEqual(40);
});

it('saves one mark in a small, fixed number of queries', function () {
    $pupils = speedPupils($this->p4, 30, 'Pupil');
    speedMarks($this->user, $this->p4, $this->maths, $pupils);
    $task = AssessmentTask::first();

    $queries = speedQueries(fn () => app(SaveScore::class)->handle($this->user, $task, $pupils[0], '17'));

    expect($queries)->toBeLessThanOrEqual(15);
});

it('opens a comment sheet in the same number of queries for 3 or 30 children', function () {
    $numeracy = Subject::where('name', 'Numeracy')->sole();
    $open = fn () => speedQueries(fn () => Livewire::actingAs($this->user)->test(CommentSheet::class, ['stream' => $this->kg1, 'subject' => $numeracy]));
    $comment = fn (array $children) => array_map(fn (Student $c) => app(SaveComment::class)->handle($this->user, Term::current()->sole(), $this->kg1, $numeracy, $c, 'Counts to 20.'), $children);

    $comment(speedPupils($this->kg1, 3, 'Small'));
    $small = $open();

    $comment(speedPupils($this->kg1, 27, 'Large'));
    $large = $open();

    expect($large)->toBe($small);
});

it('lists a teacher\'s classes in the same number of queries however many there are', function () {
    $list = fn () => speedQueries(fn () => Livewire::actingAs($this->user)->test(MyClasses::class));
    $list(); // the first render also warms the permission cache
    $few = $list();

    $staff = Staff::where('user_id', $this->user->id)->sole();
    foreach (SchoolClass::where('level', SchoolLevel::Primary)->with('streams')->get() as $class) {
        foreach ($class->streams as $stream) {
            app(AssignTeacher::class)->handle($stream, Subject::where('name', 'Science')->sole(), $staff);
        }
    }
    $many = $list();

    expect($many)->toBe($few);
});
