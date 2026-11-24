<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\BuildProgressOverview;
use App\Actions\Assessment\SaveComment;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UnlockSheet;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\WithdrawStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SheetProgress;
use App\Enums\SheetStatus;
use App\Enums\StudentStatus;
use App\Livewire\Assessment\CommentSheet;
use App\Livewire\Assessment\MarkSheet;
use App\Livewire\Assessment\MyClasses;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AssessmentComment;
use App\Models\AssessmentSheet;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function eyUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->withTwoFactor()->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function eyChild(string $first, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Darko', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

function eyComment(User $by, Stream $stream, Subject $subject, Student $child, string $text): ?AssessmentComment
{
    return app(SaveComment::class)->handle($by, Term::current()->sole(), $stream, $subject, $child, $text);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Nursery, SchoolLevel::Kg, SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());

    $this->kg1 = SchoolClass::where('name', 'KG 1')->sole()->streams()->firstOrFail();
    $this->numeracy = Subject::where('name', 'Numeracy')->sole();
    [$this->classTeacher, $staff] = eyUser(RoleKey::ClassTeacher);
    app(AssignClassTeacher::class)->handle($this->kg1, $staff);
    [$this->head] = eyUser(RoleKey::Headteacher);

    $this->abena = eyChild('Abena', $this->kg1);
    $this->kwesi = eyChild('Kwesi', $this->kg1);
});

it('gives Creche and Nursery early-years learning areas by default', function () {
    $nursery = SchoolClass::where('name', 'Nursery 1')->sole();

    expect($nursery->subjects()->orderBy('name')->pluck('name')->all())->toBe([
        'Communication and Language', 'Creative Play', 'Early Numeracy', 'Personal, Social and Emotional Development', 'Physical Development',
    ]);
});

describe('writing comments', function () {
    it('lets the class teacher write every learning area without a teaching assignment', function () {
        $comment = eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, '  Counts to 20 and   sorts shapes by colour. ');

        expect($comment->comment)->toBe('Counts to 20 and sorts shapes by colour.')
            ->and($comment->entered_by)->toBe($this->classTeacher->id)
            ->and(TaskRules::canManage($this->classTeacher, $this->kg1, Subject::where('name', 'Creative Arts')->sole(), AcademicYear::current()->sole()->id))->toBeTrue();
    });

    it('does not let a class teacher write for another class, or for a marked level', function () {
        $kg2 = SchoolClass::where('name', 'KG 2')->sole()->streams()->firstOrFail();
        expect(fn () => eyComment($this->classTeacher, $kg2, $this->numeracy, eyChild('Yaa', $kg2), 'Good.'))->toThrow(AuthorizationException::class);

        $p1 = SchoolClass::where('name', 'Primary 1')->sole()->streams()->firstOrFail();
        expect(fn () => eyComment($this->head, $p1, Subject::where('name', 'Mathematics')->sole(), eyChild('Kojo', $p1), 'Good.'))
            ->toThrow(ValidationException::class, 'Primary is assessed with marks, not comments.');
    });

    it('refuses comments over 300 characters, clears a blank one, and logs changes', function () {
        expect(fn () => eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, str_repeat('a', 301)))
            ->toThrow(ValidationException::class, 'Keep it to 300 characters');

        $comment = eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, 'Counts to 10.');
        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, 'Counts to 20.');
        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, '');

        expect(AssessmentComment::count())->toBe(0)
            ->and(Activity::where('subject_type', $comment->getMorphClass())->where('subject_id', $comment->id)->orderBy('id')->pluck('event')->all())->toBe(['updated', 'deleted']);
    });
});

describe('submitting comments', function () {
    it('needs a comment for every child still in the class', function () {
        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, 'Counts to 20.');

        try {
            app(SubmitSheet::class)->handle($this->classTeacher, Term::current()->sole(), $this->kg1, $this->numeracy);
            $this->fail('Submitted with a child missing.');
        } catch (ValidationException $e) {
            expect($e->errors()['sheet'])->toBe(['Kwesi Darko has no comment yet.']);
        }

        app(WithdrawStudent::class)->handle($this->kwesi->fresh(), StudentStatus::Withdrawn, '2026-10-27');
        $sheet = app(SubmitSheet::class)->handle($this->classTeacher, Term::current()->sole(), $this->kg1, $this->numeracy);

        expect($sheet->status)->toBe(SheetStatus::Submitted);
    });

    it('locks comments once submitted, and logs every save after the head unlocks', function () {
        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, 'Counts to 20.');
        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->kwesi, 'Knows numbers 1 to 5.');
        app(SubmitSheet::class)->handle($this->classTeacher, Term::current()->sole(), $this->kg1, $this->numeracy);

        expect(fn () => eyComment($this->head, $this->kg1, $this->numeracy, $this->abena, 'Changed.'))->toThrow(ValidationException::class, 'are locked');

        app(UnlockSheet::class)->handle($this->head, AssessmentSheet::sole(), 'Comment was for the wrong child');
        $late = eyChild('Esi', $this->kg1);
        $comment = eyComment($this->classTeacher, $this->kg1, $this->numeracy, $late, 'Settling in well.');

        expect(Activity::where('subject_type', $comment->getMorphClass())->where('subject_id', $comment->id)->where('event', 'created')->exists())->toBeTrue();
    });

    it('shows on the progress page', function () {
        $state = fn () => app(BuildProgressOverview::class)->handle(Term::current()->sole())->cell($this->kg1->id, $this->numeracy->id);

        expect($state()->progress)->toBe(SheetProgress::NotStarted)
            ->and($state()->teacher)->toBe(Staff::where('user_id', $this->classTeacher->id)->sole()->full_name);

        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->abena, 'Counts to 20.');
        expect($state()->progress)->toBe(SheetProgress::InProgress)->and($state()->percent())->toBe(50);

        eyComment($this->classTeacher, $this->kg1, $this->numeracy, $this->kwesi, 'Knows numbers 1 to 5.');
        expect($state()->progress)->toBe(SheetProgress::Ready);
    });
});

describe('the comment sheet', function () {
    beforeEach(fn () => $this->onSchool($this->school));

    it('lists the class teacher\'s learning areas and opens the comments page', function () {
        Livewire::actingAs($this->classTeacher)->test(MyClasses::class)
            ->assertSee('Numeracy')->assertSee('Comments')->assertDontSee('View only')
            ->assertSeeHtml(route('assessment.comments', [$this->kg1, $this->numeracy]));

        Livewire::actingAs($this->classTeacher)->test(MarkSheet::class, ['stream' => $this->kg1, 'subject' => $this->numeracy])
            ->assertRedirect(route('assessment.comments', [$this->kg1, $this->numeracy]));
    });

    it('saves a comment when the teacher leaves the box, and submits when everyone has one', function () {
        $page = Livewire::actingAs($this->classTeacher)->test(CommentSheet::class, ['stream' => $this->kg1, 'subject' => $this->numeracy])
            ->assertSet('texts', ['s'.$this->abena->id => '', 's'.$this->kwesi->id => ''])
            ->assertSee('Abena Darko has no comment yet.')
            ->set('texts.s'.$this->abena->id, 'Counts to 20.')
            ->set('texts.s'.$this->kwesi->id, str_repeat('b', 301))
            ->assertHasErrors('texts.s'.$this->kwesi->id)
            ->set('texts.s'.$this->kwesi->id, 'Knows numbers 1 to 5.')
            ->assertHasNoErrors()
            ->assertSee('Every child has a comment')
            ->call('submitSheet')
            ->assertSee('Submitted and locked')
            ->assertSee('Comments cannot change unless the head unlocks them');

        expect(AssessmentComment::count())->toBe(2)->and(AssessmentSheet::sole()->status)->toBe(SheetStatus::Submitted);
    });

    it('refuses someone who is neither the class teacher, an assigned teacher nor a head', function () {
        [$other] = eyUser(RoleKey::SubjectTeacher);

        $this->actingAs($other)->get(route('assessment.comments', [$this->kg1, $this->numeracy]))->assertForbidden();
        $this->actingAs($this->classTeacher)->get(route('assessment.comments', [$this->kg1, $this->numeracy]))->assertOk()->assertSee('Numeracy, KG 1A');
    });
});
