<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UnlockSheet;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Grading\SaveGradeScale;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Results\CompileClassResults;
use App\Actions\Results\SetShowPositions;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\ReportCardStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Results\ClassResults;
use App\Models\AcademicYear;
use App\Models\AssessmentSheet;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Support\Academic\Aggregates;
use App\Support\Academic\DefaultCalendar;
use App\Support\Academic\Ranking;
use App\Support\Tenancy\CurrentSchool;
use Livewire\Livewire;

function resUser(RoleKey $role): array
{
    $school = app(CurrentSchool::class)->require();
    $user = User::factory()->for($school)->withTwoFactor()->create();
    $user->assignRole($role->value);

    return [$user, Staff::factory()->for($school)->create(['user_id' => $user->id])];
}

function resPupil(string $first, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Nkrumah', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

/**
 * Gives a subject one task per part (each out of 100) and submits it with the same mark in
 * every task for each student, so a student's total equals that mark.
 *
 * @param  array<int, string>  $marks  student id => mark
 */
function resSubmit(User $head, Stream $stream, string $subjectName, array $marks): Subject
{
    $subject = Subject::where('name', $subjectName)->sole();
    $term = Term::current()->sole();
    foreach (TaskRules::schemeFor($stream, AcademicYear::current()->sole()->id)->components as $part) {
        $task = app(CreateAssessmentTask::class)->handle($head, $term, $stream, $subject, $part, ['name' => $part->name, 'given_on' => '2026-10-20', 'max_mark' => 100]);
        foreach ($marks as $studentId => $mark) {
            app(SaveScore::class)->handle($head, $task, Student::findOrFail($studentId), $mark);
        }
    }
    app(SubmitSheet::class)->handle($head, $term, $stream, $subject);

    return $subject;
}

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary, SchoolLevel::Jhs)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    app(SetUpDefaultSchemes::class)->handle(AcademicYear::current()->sole());
    app(SetUpDefaultGradeScales::class)->handle();

    [$this->head] = resUser(RoleKey::Headteacher);
    $this->jhs1 = SchoolClass::where('name', 'JHS 1')->sole()->streams()->firstOrFail();
    $this->ama = resPupil('Ama', $this->jhs1);
    $this->kofi = resPupil('Kofi', $this->jhs1);
    $this->esi = resPupil('Esi', $this->jhs1);
});

describe('the rules', function () {
    it('ranks with ties sharing a position', function () {
        expect(Ranking::positions([1 => 70.0, 2 => 85.5, 3 => 70.004, 4 => null, 5 => 60.0]))->toBe([2 => 1, 1 => 2, 3 => 2, 5 => 4])
            ->and(array_map(Ranking::ordinal(...), [1, 2, 3, 4, 11, 12, 13, 21, 22, 101, 111]))->toBe(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '101st', '111th']);
    });

    it('adds JHS aggregates: four cores and the best two others', function () {
        $points = ['English Language' => 2, 'Mathematics' => 1, 'Science' => 3, 'Social Studies' => 2, 'French' => 4, 'Computing' => 1, 'Creative Arts and Design' => 6, 'Religious and Moral Education' => 2];

        expect(Aggregates::for(SchoolLevel::Jhs, $points))->toBe(2 + 1 + 3 + 2 + 1 + 2)
            ->and(Aggregates::for(SchoolLevel::Jhs, [...$points, 'Science' => null]))->toBeNull()
            ->and(Aggregates::for(SchoolLevel::Jhs, ['English Language' => 1, 'Mathematics' => 1, 'Science' => 1, 'Social Studies' => 1, 'French' => 1]))->toBeNull()
            ->and(Aggregates::for(SchoolLevel::Primary, $points))->toBeNull();
    });

    it('adds SHS aggregates: three cores and the best three electives, not Social Studies', function () {
        $points = ['English Language' => 1, 'Core Mathematics' => 2, 'Integrated Science' => 3, 'Social Studies' => 1, 'Physics' => 4, 'Chemistry' => 2, 'Biology' => 5, 'Elective Mathematics' => 3];

        expect(Aggregates::for(SchoolLevel::Shs, $points))->toBe(1 + 2 + 3 + 2 + 3 + 4);
    });
});

describe('results from submitted sheets', function () {
    it('records each student\'s total, grade, points, parts and subject position on submit', function () {
        $english = resSubmit($this->head, $this->jhs1, 'English Language', [$this->ama->id => '81', $this->kofi->id => '64.5', $this->esi->id => '64.5']);

        $ama = TermResult::where(['student_id' => $this->ama->id, 'subject_id' => $english->id])->sole();
        $kofi = TermResult::where(['student_id' => $this->kofi->id, 'subject_id' => $english->id])->sole();

        expect([$ama->total, $ama->grade, $ama->grade_label, $ama->points, $ama->subject_position])->toBe(['81.00', '1', 'Highest', 1, 1])
            ->and([$kofi->total, $kofi->grade, $kofi->subject_position])->toBe(['64.50', '3', 2]) // 64.5 rounds to 65: grade 3
            ->and(TermResult::where(['student_id' => $this->esi->id])->sole()->subject_position)->toBe(2)
            ->and(collect($ama->parts)->pluck('weight')->all())->toBe([10, 10, 10, 70])
            ->and(collect($ama->parts)->sum('score'))->toEqual(81.0);
    });

    it('keeps the grade given even if the scale changes later', function () {
        resSubmit($this->head, $this->jhs1, 'English Language', [$this->ama->id => '81', $this->kofi->id => '50', $this->esi->id => '30']);
        app(SaveGradeScale::class)->handle(SchoolLevel::Jhs, 'Strict', [
            ['min_score' => 0, 'grade' => '9', 'label' => 'Lowest', 'points' => 9], ['min_score' => 90, 'grade' => '1', 'label' => 'Highest', 'points' => 1],
        ]);

        expect(TermResult::where('student_id', $this->ama->id)->sole()->grade)->toBe('1');
    });

    it('removes a sheet\'s results when the head unlocks it, and writes them again on submit', function () {
        $english = resSubmit($this->head, $this->jhs1, 'English Language', [$this->ama->id => '81', $this->kofi->id => '50', $this->esi->id => '30']);
        expect(TermResult::count())->toBe(3);

        app(UnlockSheet::class)->handle($this->head, AssessmentSheet::sole(), 'Wrong mark for Kofi');
        expect(TermResult::count())->toBe(0)
            ->and(ReportCard::where('student_id', $this->kofi->id)->sole()->subjects_count)->toBe(0);

        app(SubmitSheet::class)->handle($this->head, Term::current()->sole(), $this->jhs1, $english);
        expect(TermResult::count())->toBe(3);
    });
});

describe('the class result', function () {
    it('works out each student\'s average, aggregate and position in the class', function () {
        $subjects = ['English Language', 'Mathematics', 'Science', 'Social Studies', 'French', 'Computing'];
        $ama = ['81', '72', '66', '90', '55', '85'];  // grades 1, 2, 3, 1 (cores: 7) + best of 5, 1 → aggregate 13
        $kofi = ['70', '70', '70', '70', '70', '70']; // all 2 → aggregate 12
        $esi = ['40', '39', '45', '50', '60', '30'];  // grades 8, 9, 7, 6 (cores: 30) + 4, 9 → aggregate 43
        foreach ($subjects as $i => $name) {
            resSubmit($this->head, $this->jhs1, $name, [$this->ama->id => $ama[$i], $this->kofi->id => $kofi[$i], $this->esi->id => $esi[$i]]);
        }

        $card = fn (Student $s) => ReportCard::where(['student_id' => $s->id])->sole();

        expect([$card($this->ama)->average, $card($this->ama)->aggregate, $card($this->ama)->position])->toBe(['74.83', 13, 1])
            ->and([$card($this->kofi)->average, $card($this->kofi)->aggregate, $card($this->kofi)->position])->toBe(['70.00', 12, 2])
            ->and([$card($this->esi)->average, $card($this->esi)->aggregate, $card($this->esi)->position])->toBe(['44.00', 43, 3])
            ->and($card($this->ama)->class_size)->toBe(3)
            ->and($card($this->ama)->subjects_count)->toBe(6)
            ->and($card($this->ama)->status)->toBe(ReportCardStatus::Draft);
    });

    it('leaves approved and published report cards alone', function () {
        resSubmit($this->head, $this->jhs1, 'English Language', [$this->ama->id => '81', $this->kofi->id => '50', $this->esi->id => '30']);
        ReportCard::where('student_id', $this->ama->id)->sole()->update(['status' => ReportCardStatus::Published, 'average' => '99.00']);

        app(CompileClassResults::class)->handle(Term::current()->sole(), $this->jhs1);

        expect(ReportCard::where('student_id', $this->ama->id)->sole()->average)->toBe('99.00');
    });
});

describe('the results screen', function () {
    beforeEach(function () {
        resSubmit($this->head, $this->jhs1, 'English Language', [$this->ama->id => '81', $this->kofi->id => '64.5', $this->esi->id => '30']);
        $this->onSchool($this->school);
    });

    it('shows heads the grades, averages and aggregates; positions only when the school turns them on', function () {
        $page = Livewire::actingAs($this->head)->test(ClassResults::class, ['streamId' => $this->jhs1->id])
            ->set('streamId', $this->jhs1->id)
            ->assertSee('Nkrumah, Ama')->assertSee('Aggregate')->assertDontSee('Position')
            ->assertSee('1 of 11 subjects submitted');

        app(SetShowPositions::class)->handle($this->school->refresh(), true);
        app(CurrentSchool::class)->set($this->school->refresh());

        Livewire::actingAs($this->head)->test(ClassResults::class)->set('streamId', $this->jhs1->id)
            ->assertSee('Position')->assertSee('1st');
    });

    it('shows a class teacher their own class only, and refuses other staff', function () {
        [$teacher, $staff] = resUser(RoleKey::ClassTeacher);
        app(AssignClassTeacher::class)->handle($this->jhs1, $staff);
        [$other] = resUser(RoleKey::SubjectTeacher);

        $this->actingAs($teacher)->get(route('results.index'))->assertOk()->assertSee('Nkrumah, Kofi');
        $this->actingAs($other)->get(route('results.index'))->assertForbidden();
    });
});
