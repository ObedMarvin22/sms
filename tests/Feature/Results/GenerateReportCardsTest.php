<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Classes\SetClassSubjects;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Results\GenerateClassReportCards;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\ReportCardStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Jobs\GenerateReportCards;
use App\Livewire\Results\ReportCards;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function genUser(RoleKey $role): User
{
    $user = User::factory()->for(app(CurrentSchool::class)->require())->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * Gives the class one subject (Mathematics), admits the students, marks and submits.
 *
 * @return list<Student>
 */
function genClass(User $head, Stream $stream, int $students, bool $submit = true): array
{
    $maths = Subject::where('name', 'Mathematics')->sole();
    app(SetClassSubjects::class)->handle($stream->schoolClass, [$maths->id]);
    $pupils = array_map(fn (int $i) => app(AdmitStudent::class)->handle([
        'first_name' => "Pupil{$i}", 'last_name' => 'Sarpong', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08',
    ]), range(1, $students));

    $term = Term::current()->sole();
    foreach (TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components as $part) {
        $task = app(CreateAssessmentTask::class)->handle($head, $term, $stream, $maths, $part, ['name' => $part->name, 'given_on' => '2026-10-20', 'max_mark' => 100]);
        foreach ($pupils as $i => $pupil) {
            app(SaveScore::class)->handle($head, $task, $pupil, (string) (40 + $i));
        }
    }
    if ($submit) {
        app(SubmitSheet::class)->handle($head, $term, $stream, $maths);
    }

    return $pupils;
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
    app(SetUpDefaultGradeScales::class)->handle();

    $this->head = genUser(RoleKey::Headteacher);
    $this->p4 = SchoolClass::where('name', 'Primary 4')->sole()->streams()->firstOrFail();
});

it('queues a class\'s report cards 15 to a job', function () {
    genClass($this->head, $this->p4, 16);
    Queue::fake();

    expect(app(GenerateClassReportCards::class)->handle($this->head, Term::current()->sole(), $this->p4))->toBe(16);

    Queue::assertPushed(GenerateReportCards::class, 2);
    Queue::assertPushed(GenerateReportCards::class, fn (GenerateReportCards $job) => count($job->reportCardIds) === 15 && $job->school === $this->school->id);
    Queue::assertPushed(GenerateReportCards::class, fn (GenerateReportCards $job) => count($job->reportCardIds) === 1);
});

it('refuses until every subject is submitted, and refuses anyone but a head', function () {
    genClass($this->head, $this->p4, 2, submit: false);

    expect(fn () => app(GenerateClassReportCards::class)->handle($this->head, Term::current()->sole(), $this->p4))
        ->toThrow(ValidationException::class, '1 subject is not submitted yet');

    expect(fn () => app(GenerateClassReportCards::class)->handle(genUser(RoleKey::ClassTeacher), Term::current()->sole(), $this->p4))
        ->toThrow(AuthorizationException::class);
});

it('makes and stores each PDF when the job runs, leaving approved cards alone', function () {
    Storage::fake('local');
    [$first, $second] = genClass($this->head, $this->p4, 2);
    ReportCard::where('student_id', $second->id)->sole()->update(['status' => ReportCardStatus::Approved]);

    app(GenerateClassReportCards::class)->handle($this->head, Term::current()->sole(), $this->p4); // the queue runs at once in tests

    $made = ReportCard::where('student_id', $first->id)->sole();
    expect($made->status)->toBe(ReportCardStatus::Generated)
        ->and($made->getAttribute('generated_at'))->not->toBeNull()
        ->and(Storage::disk('local')->get($made->getAttribute('pdf_path')))->toStartWith('%PDF-')
        ->and(ReportCard::where('student_id', $second->id)->sole()->status)->toBe(ReportCardStatus::Approved)
        ->and(ReportCard::where('student_id', $second->id)->sole()->getAttribute('pdf_path'))->toBeNull();
});

it('shows each class on the report cards page and makes them from there', function () {
    Storage::fake('local');
    genClass($this->head, $this->p4, 3);
    $this->onSchool($this->school);

    Livewire::actingAs($this->head)->test(ReportCards::class)
        ->assertSee('Primary 4A')->assertSee('1 / 1')
        ->call('generate', $this->p4->id)
        ->assertSee('3 made');

    $this->actingAs(genUser(RoleKey::ClassTeacher))->get(route('results.report-cards'))->assertForbidden();
});
