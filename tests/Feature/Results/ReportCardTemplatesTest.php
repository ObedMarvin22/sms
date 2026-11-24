<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\SaveComment;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Results\SaveReportRemarks;
use App\Actions\Results\SetShowPositions;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Pdf\ReportCardPdf;
use App\Support\Results\ReportCardData;
use App\Support\Tenancy\CurrentSchool;

function rcUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->withTwoFactor()->create(['name' => $role->value === 'headteacher' ? 'Head Mensah' : 'Teacher Owusu']);
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id, 'first_name' => 'Kwame', 'last_name' => 'Owusu'])];
}

function rcStream(string $class): Stream
{
    return SchoolClass::where('name', $class)->sole()->streams()->firstOrFail();
}

function rcPupil(string $first, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Agyeman', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

/**
 * One task per part, each out of 100, every student given the same mark in each, then submit.
 *
 * @param  array<int, string>  $marks  student id => mark
 */
function rcSubmit(User $head, Stream $stream, string $subjectName, array $marks): void
{
    $subject = Subject::where('name', $subjectName)->sole();
    $term = Term::current()->sole();
    foreach (TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components as $part) {
        $task = app(CreateAssessmentTask::class)->handle($head, $term, $stream, $subject, $part, ['name' => $part->name, 'given_on' => '2026-10-20', 'max_mark' => 100]);
        foreach ($marks as $id => $mark) {
            app(SaveScore::class)->handle($head, $task, Student::findOrFail($id), $mark);
        }
    }
    app(SubmitSheet::class)->handle($head, $term, $stream, $subject);
}

function rcHtml(ReportCard $card): string
{
    $data = ReportCardData::for(app(CurrentSchool::class)->require(), $card);

    return view('pdf.report-cards.'.$data->template, ['d' => $data])->render();
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs)
        ->create(['subdomain' => 'greenhill', 'name' => 'Greenhill Academy']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());
    app(SetUpDefaultGradeScales::class)->handle();
    [$this->head] = rcUser(RoleKey::Headteacher);
});

it('prints a JHS report card: class score, exam, total, grade, average, aggregate and remarks', function () {
    $jhs1 = rcStream('JHS 1');
    $ama = rcPupil('Ama', $jhs1);
    $kofi = rcPupil('Kofi', $jhs1);
    rcSubmit($this->head, $jhs1, 'English Language', [$ama->id => '81', $kofi->id => '50']);
    rcSubmit($this->head, $jhs1, 'Mathematics', [$ama->id => '64.5', $kofi->id => '70']);
    app(SaveReportRemarks::class)->handle($this->head, Term::current()->sole(), $jhs1, $ama, [
        'teacher_remark' => 'A careful and kind student.', 'conduct' => 'very_good', 'interest' => 'Reading', 'head_remark' => 'Excellent work.',
    ]);

    $html = rcHtml(ReportCard::where('student_id', $ama->id)->sole());

    expect($html)
        ->toContain('Greenhill Academy')->toContain('End of term report')->toContain('Ama Agyeman')->toContain('JHS 1A')
        ->toContain('Class score (30)')->toContain('Exam (70)')
        ->toContain('English Language')->toContain('<td class="num">24.3</td>') // 81 × 30% = 24.3 class score
        ->toContain('<td class="grade">1</td>')->toContain('Highest')
        ->toContain('<td class="grade">3</td>') // 64.5 rounds to 65: grade 3
        ->toContain('Average: <span class="big">72.8</span>') // (81 + 64.5) ÷ 2 = 72.75
        ->toContain('Aggregate:') // not enough subjects yet for a full aggregate
        ->toContain('Very good')->toContain('Reading')->toContain('A careful and kind student.')->toContain('Excellent work.')
        ->toContain('Next term begins')->not->toContain('Position');
});

it('shows positions only when the school turns them on', function () {
    $jhs1 = rcStream('JHS 1');
    $ama = rcPupil('Ama', $jhs1);
    rcSubmit($this->head, $jhs1, 'English Language', [$ama->id => '81']);
    app(SetShowPositions::class)->handle($this->school, true);
    app(CurrentSchool::class)->set($this->school->refresh());

    expect(rcHtml(ReportCard::where('student_id', $ama->id)->sole()))->toContain('Position')->toContain('1st');
});

it('prints a primary report card with NaCCA grades and no aggregate', function () {
    $p4 = rcStream('Primary 4');
    $yaw = rcPupil('Yaw', $p4);
    rcSubmit($this->head, $p4, 'Mathematics', [$yaw->id => '70']);

    $html = rcHtml(ReportCard::where('student_id', $yaw->id)->sole());

    expect($html)->toContain('Proficient')->toContain('<td class="grade">P</td>')->not->toContain('Aggregate')
        ->toContain('HP Highly proficient (80–100)');
});

it('prints an early-years report card with the comment for each learning area', function () {
    $kg1 = rcStream('KG 1');
    [$teacher, $staff] = rcUser(RoleKey::ClassTeacher);
    app(AssignClassTeacher::class)->handle($kg1, $staff);
    $abena = rcPupil('Abena', $kg1);
    app(SaveComment::class)->handle($teacher, Term::current()->sole(), $kg1, Subject::where('name', 'Numeracy')->sole(), $abena, 'Counts to 20 and sorts shapes.');
    app(SubmitSheet::class)->handle($teacher, Term::current()->sole(), $kg1, Subject::where('name', 'Numeracy')->sole());

    $html = rcHtml(ReportCard::where('student_id', $abena->id)->sole());

    expect($html)->toContain('Learning area')->toContain('Numeracy')->toContain('Counts to 20 and sorts shapes.')
        ->toContain('Kwame Owusu')->not->toContain('Grade');
});

it('renders a real PDF for each kind of report card', function () {
    $jhs1 = rcStream('JHS 1');
    $ama = rcPupil('Ama', $jhs1);
    rcSubmit($this->head, $jhs1, 'English Language', [$ama->id => '81']);

    $pdf = app(ReportCardPdf::class)->render(ReportCard::where('student_id', $ama->id)->sole());

    expect(substr($pdf, 0, 5))->toBe('%PDF-')->and(strlen($pdf))->toBeGreaterThan(3000);
});

it('lets a head or the class teacher preview a report card, and no one else', function () {
    $jhs1 = rcStream('JHS 1');
    $ama = rcPupil('Ama', $jhs1);
    rcSubmit($this->head, $jhs1, 'English Language', [$ama->id => '81']);
    $card = ReportCard::where('student_id', $ama->id)->sole();
    [$classTeacher, $staff] = rcUser(RoleKey::ClassTeacher);
    app(AssignClassTeacher::class)->handle($jhs1, $staff);
    [$other] = rcUser(RoleKey::SubjectTeacher);
    $this->onSchool($this->school);

    $this->actingAs($this->head)->get(route('results.report-card', $card))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($classTeacher)->get(route('results.report-card', $card))->assertOk();
    $this->actingAs($other)->get(route('results.report-card', $card))->assertForbidden();
});
