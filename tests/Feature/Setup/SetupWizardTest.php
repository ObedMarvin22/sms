<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\CreateClass;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Schools\CreateSchool;
use App\Actions\Schools\UpdateSchoolProfile;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SetupStep;
use App\Http\Middleware\EnsureSchoolReadyForStudents;
use App\Livewire\Setup\Wizard;
use App\Models\School;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Settings\SchoolSettings;
use App\Support\Setup\SetupProgress;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)
        ->create(['subdomain' => 'greenhill', 'phone' => null]);
    app(CurrentSchool::class)->set($this->school);
    $this->progress = app(SetupProgress::class);
});

function completeProfile(School $school): void
{
    app(UpdateSchoolProfile::class)->handle($school, ['name' => $school->name, 'phone' => '0244123456', 'levels' => ['primary']]);
}

function createCurrentYear(): void
{
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
}

/**
 * @return array<string, string> step => done|open|locked
 */
function states(SetupProgress $progress): array
{
    $states = [];
    foreach (SetupStep::cases() as $step) {
        $states[$step->value] = $progress->isDone($step) ? 'done' : ($progress->isOpen($step) ? 'open' : 'locked');
    }

    return $states;
}

it('starts with only the first step open', function () {
    expect(states($this->progress))->toBe([
        'profile' => 'open', 'academic_year' => 'locked', 'classes' => 'locked', 'subjects' => 'locked', 'class_subjects' => 'locked',
    ])->and($this->progress->nextStep())->toBe(SetupStep::Profile);
});

it('opens each step once the ones before it are done', function () {
    completeProfile($this->school);
    expect(states($this->progress)['academic_year'])->toBe('open')
        ->and(states($this->progress)['classes'])->toBe('locked');

    createCurrentYear();
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();

    expect(states($this->progress))->toBe([
        'profile' => 'done', 'academic_year' => 'done', 'classes' => 'done', 'subjects' => 'done', 'class_subjects' => 'open',
    ]);
});

it('keeps class subjects open while any class has no subjects', function () {
    completeProfile($this->school);
    createCurrentYear();
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
    expect($this->progress->isComplete())->toBeTrue();

    app(CreateClass::class)->handle(SchoolLevel::Primary, 'Primary 7');

    expect($this->progress->isDone(SetupStep::ClassSubjects))->toBeFalse()
        ->and($this->progress->nextStep())->toBe(SetupStep::ClassSubjects);
});

it('takes a brand-new school from nothing to ready using only the setup Actions', function () {
    Notification::fake();
    app(CurrentSchool::class)->clear();

    // As the console would: a new school with no profile details, no years, no classes.
    $school = app(CreateSchool::class)->handle('Ridge Academy', 'ridge', ['kg', 'primary'], 'Kojo Annan', 'kojo@ridge.test');

    app(CurrentSchool::class)->runFor($school, function (School $school) {
        $progress = app(SetupProgress::class);
        expect($progress->readyForStudents())->toBeFalse();

        app(UpdateSchoolProfile::class)->handle($school, ['name' => 'Ridge Academy', 'phone' => '0302 123 456', 'levels' => ['kg', 'primary']]);
        createCurrentYear();
        expect($progress->readyForStudents())->toBeFalse(); // no class yet

        app(GenerateDefaultClasses::class)->handle();
        expect($progress->readyForStudents())->toBeTrue();

        app(GenerateDefaultSubjects::class)->handle();
        app(ApplyDefaultClassSubjects::class)->handle();

        expect($progress->isComplete())->toBeTrue();
    });
});

describe('ready for students', function () {
    beforeEach(function () {
        Route::domain('{school}.'.config('app.base_domain'))->middleware(['web', 'school', 'school.ready'])
            ->get('/_students-probe', fn () => 'students ok');
        Route::domain('{school}.'.config('app.base_domain'))->middleware(['web', 'school', 'school.ready'])
            ->get('/api/_students-probe', fn () => ['ok' => true]);
    });

    it('blocks student screens until there is a current term and a class', function () {
        $this->get('http://greenhill.localhost/_students-probe')
            ->assertRedirect('http://greenhill.localhost/setup')
            ->assertSessionHas('status', EnsureSchoolReadyForStudents::MESSAGE);
        $this->getJson('http://greenhill.localhost/api/_students-probe')->assertStatus(409);

        createCurrentYear();
        $this->get('http://greenhill.localhost/_students-probe')->assertRedirect();

        app(CreateClass::class)->handle(SchoolLevel::Primary, 'Primary 1');
        $this->get('http://greenhill.localhost/_students-probe')->assertOk()->assertSee('students ok');
    });
});

describe('screens', function () {
    beforeEach(function () {
        app(SeedDefaultRoles::class)->handle($this->school);
        $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
        $this->teacher = User::factory()->for($this->school)->create();
        app(CurrentSchool::class)->runFor($this->school, function () {
            $this->admin->assignRole(RoleKey::Administrator->value);
            $this->teacher->assignRole(RoleKey::ClassTeacher->value);
        });
        $this->onSchool($this->school);
    });

    it('refuses teachers', function () {
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/setup')->assertForbidden();
    });

    it('shows the steps with their state', function () {
        completeProfile($this->school);

        $this->actingAs($this->admin)->get('http://greenhill.localhost/setup')
            ->assertOk()
            ->assertSee('data-test="step-profile" data-state="done"', false)
            ->assertSee('data-test="step-academic_year" data-state="open"', false)
            ->assertSee('data-test="step-classes" data-state="locked"', false)
            ->assertSee('Setup checklist');
    });

    it('runs quick actions only for open steps, and records completion', function () {
        completeProfile($this->school);

        Livewire::actingAs($this->admin)->test(Wizard::class)->call('generateClasses')->assertForbidden();

        createCurrentYear();
        Livewire::actingAs($this->admin)->test(Wizard::class)
            ->call('generateClasses')
            ->call('generateSubjects')
            ->call('applyClassSubjects')
            ->assertSee('Your school is ready');

        expect(SchoolSettings::for($this->school->fresh())->get('setup_completed_at'))->not->toBeNull();
    });

    it('shows the dashboard banner to administrators until setup is complete', function () {
        $this->actingAs($this->admin)->get('http://greenhill.localhost/dashboard')->assertSee('Finish setting up your school');
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/dashboard')->assertDontSee('Finish setting up your school');

        completeProfile($this->school);
        createCurrentYear();
        app(GenerateDefaultClasses::class)->handle();
        app(GenerateDefaultSubjects::class)->handle();
        app(ApplyDefaultClassSubjects::class)->handle();

        $this->actingAs($this->admin)->get('http://greenhill.localhost/dashboard')->assertDontSee('Finish setting up your school');
    });
});
