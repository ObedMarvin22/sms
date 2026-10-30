<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Academic\DeleteAcademicYear;
use App\Actions\Academic\SetAcademicPeriodType;
use App\Actions\Academic\SetCurrentTerm;
use App\Actions\Academic\UpdateTermDates;
use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Livewire\Academic\CreateYear;
use App\Livewire\Academic\Years;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
});

/**
 * Create a year from the default calendar, with optional overrides.
 */
function makeYear(int $startYear = 2026, array $overrides = []): AcademicYear
{
    $period = SchoolSettings::for(app(CurrentSchool::class)->require()->refresh())->academicPeriod();
    $data = array_replace_recursive(DefaultCalendar::for($startYear, $period), $overrides);

    return app(CreateAcademicYear::class)->handle($data['name'], $data['starts_on'], $data['ends_on'], $data['terms']);
}

function yearErrors(int $startYear, array $overrides): array
{
    try {
        makeYear($startYear, $overrides);
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

it('creates a year with three terms and makes the first one current', function () {
    $year = makeYear();

    expect($year->name)->toBe('2026/2027')
        ->and($year->terms->pluck('name')->all())->toBe(['First term', 'Second term', 'Third term'])
        ->and($year->terms->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and($year->fresh()->is_current)->toBeTrue()
        ->and(Term::current()->sole()->sequence)->toBe(1)
        ->and($year->terms[0]->census_on->toDateString())->toBe('2026-09-22');
});

it('creates two semesters for a semester school', function () {
    app(SetAcademicPeriodType::class)->handle($this->school, AcademicPeriodType::Semester);

    expect(makeYear()->terms->pluck('name')->all())->toBe(['First semester', 'Second semester']);
});

it('does not change the current term when another year is added', function () {
    makeYear(2026);
    $next = makeYear(2027);

    expect($next->fresh()->is_current)->toBeFalse()
        ->and(Term::current()->sole()->academicYear->name)->toBe('2026/2027');
});

it('fixes terms-or-semesters once a year exists', function () {
    makeYear();

    expect(fn () => app(SetAcademicPeriodType::class)->handle($this->school, AcademicPeriodType::Semester))
        ->toThrow(ValidationException::class, 'cannot change');
});

it('rejects invalid years', function (array $overrides, string $errorKey) {
    expect(yearErrors(2026, $overrides))->toContain($errorKey);
    expect(AcademicYear::count())->toBe(0);
})->with([
    'bad name' => [['name' => '2026-2027'], 'name'],
    'name years not consecutive' => [['name' => '2026/2028'], 'name'],
    'year ends before it starts' => [['ends_on' => '2026-08-01'], 'ends_on'],
    'not a real date' => [['starts_on' => '2026-02-30'], 'starts_on'],
    'term outside the year' => [['terms' => [0 => ['starts_on' => '2026-08-15']]], 'terms.0.starts_on'],
    'term ends before it starts' => [['terms' => [1 => ['ends_on' => '2027-01-01']]], 'terms.1.ends_on'],
    'census outside its term' => [['terms' => [2 => ['census_on' => '2027-08-01']]], 'terms.2.census_on'],
    'overlapping terms' => [['terms' => [1 => ['starts_on' => '2026-12-01', 'census_on' => '2026-12-10']]], 'terms.1.starts_on'],
]);

it('rejects a wrong number of terms', function () {
    expect(fn () => app(CreateAcademicYear::class)->handle('2026/2027', '2026-09-01', '2027-07-31', [
        ['starts_on' => '2026-09-08', 'ends_on' => '2026-12-18', 'census_on' => '2026-09-22'],
    ]))->toThrow(ValidationException::class, 'needs 3');
});

it('rejects a duplicate or overlapping year', function () {
    makeYear(2026);

    expect(yearErrors(2026, []))->toContain('name');
    expect(yearErrors(2027, ['starts_on' => '2027-07-01']))->toContain('starts_on');
});

it('switches the current term and year together, and logs it', function () {
    $first = makeYear(2026);
    $second = makeYear(2027);
    $target = $second->terms[1];

    app(SetCurrentTerm::class)->handle($target);

    expect(Term::current()->sole()->is($target))->toBeTrue()
        ->and(AcademicYear::current()->sole()->is($second))->toBeTrue()
        ->and($first->fresh()->is_current)->toBeFalse();

    $entry = Activity::where('log_name', 'academic')->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)->latest('id')->firstOrFail();
    expect($entry->attribute_changes['attributes']['is_current'])->toBeTrue();
});

it('lets the database refuse a second current term', function () {
    $year = makeYear();

    // Bypass the Action: two "true" rows for one school must be impossible.
    expect(fn () => $year->terms[1]->forceFill(['is_current' => true])->save())
        ->toThrow(QueryException::class);
});

it('stores "not current" as NULL, never false', function () {
    $year = makeYear();

    expect(DB::table('terms')->where('id', $year->terms[1]->id)->value('is_current'))->toBeNull();
});

it('updates term dates within the rules', function () {
    $year = makeYear();
    $update = app(UpdateTermDates::class);

    $update->handle($year->terms[0], '2026-09-10', '2026-12-17', '2026-09-24');
    expect($year->terms[0]->fresh()->starts_on->toDateString())->toBe('2026-09-10');

    expect(fn () => $update->handle($year->terms[0], '2026-09-10', '2027-01-20', '2026-09-24'))
        ->toThrow(ValidationException::class, 'overlap');
    expect(fn () => $update->handle($year->terms[1], '2027-01-12', '2027-04-02', '2027-05-01'))
        ->toThrow(ValidationException::class);
});

it('deletes a year that is not current, with its terms', function () {
    makeYear(2026);
    $next = makeYear(2027);

    app(DeleteAcademicYear::class)->handle($next);

    expect(AcademicYear::count())->toBe(1)
        ->and(Term::count())->toBe(3);
});

it('refuses to delete the current year', function () {
    expect(fn () => app(DeleteAcademicYear::class)->handle(makeYear()))
        ->toThrow(ValidationException::class, 'current academic year');
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

    it('refuses teachers', function (string $path) {
        $this->actingAs($this->teacher)->get("http://greenhill.localhost{$path}")->assertForbidden();
    })->with(['/settings/academic-years', '/settings/academic-years/create']);

    it('lists years for administrators and links from the sidebar', function () {
        makeYear();

        $this->actingAs($this->admin)
            ->get('http://greenhill.localhost/settings/academic-years')
            ->assertOk()
            ->assertSee('2026/2027')
            ->assertSee('Third term')
            ->assertSee('Academic years');
    });

    it('creates a year from the prefilled form', function () {
        Livewire::actingAs($this->admin)->test(CreateYear::class)
            ->assertSet('terms', fn (array $terms) => count($terms) === 3)
            ->set('name', '2026/2027')
            ->set('starts_on', '2026-09-01')
            ->set('ends_on', '2027-07-31')
            ->set('terms', DefaultCalendar::for(2026, AcademicPeriodType::Term)['terms'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('academic-years.index'));

        expect(AcademicYear::sole()->terms)->toHaveCount(3);
    });

    it('shows Action errors on the matching fields', function () {
        Livewire::actingAs($this->admin)->test(CreateYear::class)
            ->set('terms.2.census_on', '2030-01-01')
            ->call('save')
            ->assertHasErrors('terms.2.census_on');
    });

    it('makes a term current from the list', function () {
        $year = makeYear();

        Livewire::actingAs($this->admin)->test(Years::class)
            ->call('makeCurrent', $year->terms[2]->id)
            ->assertHasNoErrors();

        expect(Term::current()->sole()->sequence)->toBe(3);
    });

    it('stops a teacher calling the list actions directly', function () {
        Livewire::actingAs($this->teacher)->test(Years::class)->assertForbidden();
    });
});
