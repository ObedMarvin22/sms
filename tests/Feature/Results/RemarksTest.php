<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\TakeAttendance;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Results\FillSuggestedHeadRemarks;
use App\Actions\Results\SaveReportRemarks;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\ReportCardStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Results\Remarks;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Results\AttendanceSummary;
use App\Support\Results\HeadRemarks;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function remUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->withTwoFactor()->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function remPupil(string $first, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Ofori', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

/**
 * @param  array<int, string>  $statuses  student id => status; everyone else present
 */
function remRegister(User $teacher, Stream $stream, string $date, array $statuses = []): void
{
    $marks = [];
    foreach (AttendanceRules::enrolmentsOn($stream, CarbonImmutable::parse($date)) as $enrolment) {
        $marks[$enrolment->student_id] = ['status' => $statuses[$enrolment->student_id] ?? 'present', 'note' => ''];
    }
    app(TakeAttendance::class)->handle($teacher, $stream, $date, $marks);
}

function remSave(User $by, Stream $stream, Student $student, array $fields): ReportCard
{
    return app(SaveReportRemarks::class)->handle($by, Term::current()->sole(), $stream, $student, $fields);
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

    $this->p4 = SchoolClass::where('name', 'Primary 4')->sole()->streams()->firstOrFail();
    [$this->classTeacher, $staff] = remUser(RoleKey::ClassTeacher);
    app(AssignClassTeacher::class)->handle($this->p4, $staff);
    [$this->head] = remUser(RoleKey::Headteacher);
    $this->ama = remPupil('Ama', $this->p4);
    $this->kofi = remPupil('Kofi', $this->p4);
});

describe('saving remarks', function () {
    it('lets the class teacher write the remark, conduct and interest, but not the head\'s remark', function () {
        $card = remSave($this->classTeacher, $this->p4, $this->ama, ['teacher_remark' => '  Hardworking and   kind. ', 'conduct' => 'very_good', 'interest' => 'Football']);

        expect([$card->getAttribute('teacher_remark'), $card->getAttribute('conduct'), $card->getAttribute('interest')])->toBe(['Hardworking and kind.', 'very_good', 'Football'])
            ->and($card->status)->toBe(ReportCardStatus::Draft)
            ->and(fn () => remSave($this->classTeacher, $this->p4, $this->ama, ['head_remark' => 'Good.']))->toThrow(AuthorizationException::class)
            ->and(remSave($this->head, $this->p4, $this->ama, ['head_remark' => 'Well done.'])->getAttribute('head_remark'))->toBe('Well done.');
    });

    it('refuses other teachers, unknown conduct, long remarks and children not in the class', function () {
        [$other] = remUser(RoleKey::SubjectTeacher);
        $p5 = SchoolClass::where('name', 'Primary 5')->sole()->streams()->firstOrFail();

        expect(fn () => remSave($other, $this->p4, $this->ama, ['teacher_remark' => 'Hi']))->toThrow(AuthorizationException::class)
            ->and(fn () => remSave($this->classTeacher, $this->p4, $this->ama, ['conduct' => 'superb']))->toThrow(ValidationException::class, 'Choose a conduct')
            ->and(fn () => remSave($this->classTeacher, $this->p4, $this->ama, ['teacher_remark' => str_repeat('a', 401)]))->toThrow(ValidationException::class, '400 characters')
            ->and(fn () => remSave($this->head, $this->p4, remPupil('Yaw', $p5), ['teacher_remark' => 'Hi']))->toThrow(ValidationException::class, 'is not in Primary 4A');
    });

    it('keeps approved and published report cards as they are', function () {
        remSave($this->classTeacher, $this->p4, $this->ama, ['teacher_remark' => 'Good.']);
        ReportCard::where('student_id', $this->ama->id)->sole()->update(['status' => ReportCardStatus::Approved]);

        remSave($this->head, $this->p4, $this->ama, ['head_remark' => 'Changed.']);
    })->throws(ValidationException::class, 'already Approved');
});

describe('suggested head\'s remarks and attendance', function () {
    it('suggests a head\'s remark from the average, without replacing ones already written', function () {
        remSave($this->classTeacher, $this->p4, $this->ama, ['teacher_remark' => 'Good.']);
        ReportCard::where('student_id', $this->ama->id)->sole()->update(['average' => '82.00']);
        remSave($this->head, $this->p4, $this->kofi, ['head_remark' => 'My own words.']);

        expect(app(FillSuggestedHeadRemarks::class)->handle($this->head, Term::current()->sole(), $this->p4))->toBe(1)
            ->and(ReportCard::where('student_id', $this->kofi->id)->sole()->getAttribute('head_remark'))->toBe('My own words.')
            ->and(HeadRemarks::suggest(82.0))->toBe('Excellent results. Keep it up.')
            ->and(HeadRemarks::suggest(null))->toStartWith('A good term');

        expect(fn () => app(FillSuggestedHeadRemarks::class)->handle($this->classTeacher, Term::current()->sole(), $this->p4))->toThrow(AuthorizationException::class);
    });

    it('counts days present (late counts) out of days on the register this term', function () {
        // Registers lock at 10:00 on their day, so each is taken that morning.
        $this->travelTo('2026-10-26 08:00:00');
        remRegister($this->classTeacher, $this->p4, '2026-10-26');
        $this->travelTo('2026-10-27 08:00:00');
        remRegister($this->classTeacher, $this->p4, '2026-10-27', [$this->ama->id => 'absent']);
        $this->travelTo('2026-10-28 08:00:00');
        remRegister($this->classTeacher, $this->p4, '2026-10-28', [$this->ama->id => 'late', $this->kofi->id => 'excused']);

        expect(AttendanceSummary::for(Term::current()->sole(), [$this->ama->id, $this->kofi->id]))->toBe([
            $this->ama->id => ['present' => 2, 'total' => 3],
            $this->kofi->id => ['present' => 2, 'total' => 3],
        ]);
    });
});

describe('the remarks page', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('saves a remark when the class teacher leaves the box', function () {
        Livewire::actingAs($this->classTeacher)->test(Remarks::class)
            ->assertSee('Ama Ofori')->assertDontSeeHtml('data-test="fill-suggested"')
            ->set('remarks.s'.$this->ama->id.'.teacher_remark', 'Reads well and helps others.')
            ->set('remarks.s'.$this->ama->id.'.conduct', 'excellent')
            ->assertHasNoErrors();

        $card = ReportCard::where('student_id', $this->ama->id)->sole();
        expect([$card->getAttribute('teacher_remark'), $card->getAttribute('conduct')])->toBe(['Reads well and helps others.', 'excellent']);
    });

    it('lets the head fill suggested remarks; refuses staff who are neither', function () {
        Livewire::actingAs($this->head)->test(Remarks::class)->set('streamId', $this->p4->id)
            ->call('fillSuggested')
            ->assertSet('remarks.s'.$this->ama->id.'.head_remark', HeadRemarks::suggest(null));

        [$other] = remUser(RoleKey::SubjectTeacher);
        $this->actingAs($other)->get(route('results.remarks'))->assertForbidden();
    });
});
