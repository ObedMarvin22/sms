<?php

use App\Actions\Assessment\BuildProgressOverview;
use App\Actions\Assessment\TaskRules;
use App\Enums\SheetProgress;
use App\Enums\SheetStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentComment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAssessmentSeeder;

it('gives the demo schools a term of marks, comments and sheets in every state, once', function () {
    $this->seed(DatabaseSeeder::class); // skips assessment under tests
    $this->seed(DemoAssessmentSeeder::class);

    $greenhill = School::where('subdomain', 'greenhill')->sole();
    $counts = fn () => app(CurrentSchool::class)->runFor($greenhill, fn () => [
        AssessmentTask::count(), AssessmentScore::count(), AssessmentComment::count(), AssessmentSheet::count(),
    ]);
    $before = $counts();

    app(CurrentSchool::class)->runFor($greenhill, function () {
        $term = Term::current()->sole();
        $sheets = AssessmentSheet::where('term_id', $term->id)->get();

        expect(AssessmentTask::count())->toBeGreaterThan(100)
            ->and(AssessmentScore::count())->toBeGreaterThan(500)
            ->and(AssessmentComment::count())->toBeGreaterThan(10)
            ->and($sheets->where('status', SheetStatus::Submitted)->count())->toBeGreaterThan(10)
            ->and($sheets->where('status', SheetStatus::Reopened))->toHaveCount(1)
            ->and(AssessmentScore::where('mark', '<', 0)->exists())->toBeFalse();

        // Every state shows up on the head's progress page.
        $states = collect(app(BuildProgressOverview::class)->handle($term)->cells())->map(fn ($c) => $c->progress)->unique();
        expect($states)->toContain(SheetProgress::Submitted, SheetProgress::Ready, SheetProgress::InProgress, SheetProgress::Reopened);

        // The demo teacher's own Mathematics is left part-filled to try.
        $p4 = SchoolClass::where('name', 'Primary 4')->sole()->streams()->firstOrFail();
        $maths = Subject::where('name', 'Mathematics')->sole();
        expect(app(BuildProgressOverview::class)->handle($term)->cell($p4->id, $maths->id)->progress)->toBe(SheetProgress::InProgress);

        // The KG class teacher can write KG 1A's comments.
        $kgTeacher = User::where('email', 'kg@greenhill.test')->sole();
        $kg1 = SchoolClass::where('name', 'KG 1')->sole()->streams()->firstOrFail();
        expect(TaskRules::canManage($kgTeacher, $kg1, Subject::where('name', 'Numeracy')->sole(), AcademicYear::current()->sole()->id))->toBeTrue();
    });

    $this->seed(DemoAssessmentSeeder::class);
    expect($counts())->toBe($before);
});
