<?php

use App\Actions\Academic\DeleteAcademicYear;
use App\Actions\Assessment\CopySchemesFromYear;
use App\Actions\Assessment\SaveAssessmentScheme;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Assessment\Schemes;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    $this->year = AcademicYear::factory()->for($this->school)->create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
});

/**
 * @return array<string, int>
 */
function schemeWeights(AcademicYear $year, SchoolLevel $level): array
{
    return AssessmentScheme::with('components')->where('academic_year_id', $year->id)->where('level', $level)->sole()
        ->components->pluck('weight', 'name')->all();
}

/**
 * @param  array<string, int>  $weights  name => weight
 * @return list<array{name: string, kind: ComponentKind, weight: int}>
 */
function schemeParts(array $weights): array
{
    return array_map(fn (string $name, int $weight) => [
        'name' => $name,
        'kind' => str_contains(strtolower($name), 'exam') ? ComponentKind::Exam : ComponentKind::Test,
        'weight' => $weight,
    ], array_keys($weights), $weights);
}

describe('the standard setup', function () {
    it('gives KG comments only and the other levels 30% CA and a 70% exam', function () {
        expect(app(SetUpDefaultSchemes::class)->handle($this->year))->toBe(3);

        $kg = AssessmentScheme::where('level', SchoolLevel::Kg)->sole();
        expect($kg->mode)->toBe(AssessmentMode::Descriptive)
            ->and($kg->components)->toBeEmpty()
            ->and(schemeWeights($this->year, SchoolLevel::Primary))->toBe([
                'Class exercises' => 10, 'Class tests' => 10, 'Project' => 10, 'End of term exam' => 70,
            ])
            ->and(AssessmentScheme::where('level', SchoolLevel::Jhs)->sole()->mode)->toBe(AssessmentMode::Weighted)
            ->and(AssessmentComponent::where('kind', ComponentKind::Exam)->count())->toBe(2);
    });

    it('leaves levels already set up alone when run again', function () {
        app(SaveAssessmentScheme::class)->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, schemeParts(['Tests' => 40, 'Exam' => 60]));

        expect(app(SetUpDefaultSchemes::class)->handle($this->year))->toBe(2)
            ->and(app(SetUpDefaultSchemes::class)->handle($this->year))->toBe(0)
            ->and(schemeWeights($this->year, SchoolLevel::Primary))->toBe(['Tests' => 40, 'Exam' => 60]);
    });
});

describe('saving a scheme', function () {
    it('needs weights that add up to 100', function () {
        app(SaveAssessmentScheme::class)->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, schemeParts(['Tests' => 40, 'Exam' => 50]));
    })->throws(ValidationException::class, 'The weights add up to 90%. They must add up to 100%.');

    it('refuses bad parts', function (array $parts, string $field) {
        try {
            app(SaveAssessmentScheme::class)->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, $parts);
            $this->fail('No error.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }
        expect(AssessmentScheme::count())->toBe(0);
    })->with([
        'no parts' => [[], 'parts'],
        'nine parts' => [array_map(fn ($i) => ['name' => "Part $i", 'kind' => 'test', 'weight' => $i === 9 ? 20 : 10], range(1, 9)), 'parts'],
        'blank name' => [[['name' => '  ', 'kind' => 'exam', 'weight' => 100]], 'parts.0.name'],
        'same name twice' => [[['name' => 'Tests', 'kind' => 'test', 'weight' => 50], ['name' => 'tests ', 'kind' => 'exam', 'weight' => 50]], 'parts.1.name'],
        'unknown kind' => [[['name' => 'Tests', 'kind' => 'quiz', 'weight' => 100]], 'parts.0.kind'],
        'zero weight' => [[['name' => 'Tests', 'kind' => 'test', 'weight' => 0], ['name' => 'Exam', 'kind' => 'exam', 'weight' => 100]], 'parts.0.weight'],
        'fractional weight' => [[['name' => 'Tests', 'kind' => 'test', 'weight' => '12.5'], ['name' => 'Exam', 'kind' => 'exam', 'weight' => '87.5']], 'parts.0.weight'],
    ]);

    it('refuses a level the school does not offer', function () {
        app(SaveAssessmentScheme::class)->handle($this->year, SchoolLevel::Shs, AssessmentMode::Weighted, schemeParts(['Exam' => 100]));
    })->throws(ValidationException::class, 'Choose a level the school offers.');

    it('keeps the ids of parts passed back, removes the rest and logs the changes', function () {
        $save = app(SaveAssessmentScheme::class);
        $scheme = $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, schemeParts(['Tests' => 20, 'Project' => 10, 'Exam' => 70]));
        [$tests, $project, $exam] = $scheme->components->all();

        $scheme = $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, [
            ['id' => $exam->id, 'name' => 'Exam', 'kind' => 'exam', 'weight' => '60'],
            ['id' => $tests->id, 'name' => 'Class tests', 'kind' => 'test', 'weight' => 40],
        ]);

        expect($scheme->components->pluck('id')->all())->toBe([$exam->id, $tests->id])
            ->and(schemeWeights($this->year, SchoolLevel::Jhs))->toBe(['Exam' => 60, 'Class tests' => 40])
            ->and(AssessmentComponent::find($project->id))->toBeNull()
            ->and(AssessmentScheme::count())->toBe(1);

        $renamed = Activity::where('subject_type', $tests->getMorphClass())->where('subject_id', $tests->id)->where('event', 'updated')->sole();
        expect($renamed->log_name)->toBe('grades')
            ->and($renamed->attribute_changes['attributes'])->toMatchArray(['name' => 'Class tests', 'weight' => 40]);
        expect(Activity::where('subject_id', $project->id)->where('subject_type', $project->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
    });

    it('swaps two part names in one save', function () {
        $save = app(SaveAssessmentScheme::class);
        [$tests, $exam] = $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, schemeParts(['Tests' => 30, 'Exam' => 70]))->components->all();

        $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, [
            ['id' => $tests->id, 'name' => 'Exam', 'kind' => 'exam', 'weight' => 30],
            ['id' => $exam->id, 'name' => 'Tests', 'kind' => 'test', 'weight' => 70],
        ]);

        expect($tests->fresh()->name)->toBe('Exam')->and($exam->fresh()->name)->toBe('Tests');

        $log = Activity::where('subject_type', $tests->getMorphClass())->where('subject_id', $tests->id)->where('event', 'updated')->sole();
        expect($log->attribute_changes['old']['name'])->toBe('Tests')
            ->and($log->attribute_changes['attributes']['name'])->toBe('Exam');
    });

    it('refuses a part id from another scheme without renaming anything', function () {
        $save = app(SaveAssessmentScheme::class);
        $other = $save->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, schemeParts(['Exam' => 100]))->components->sole();
        $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, schemeParts(['Exam' => 100]));

        expect(fn () => $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, [['id' => $other->id, 'name' => 'Final', 'kind' => 'exam', 'weight' => 100]]))
            ->toThrow(ModelNotFoundException::class)
            ->and($other->fresh()->name)->toBe('Exam');
    });

    it('drops the parts when a level switches to comments only', function () {
        $save = app(SaveAssessmentScheme::class);
        $save->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, schemeParts(['Tests' => 30, 'Exam' => 70]));
        $scheme = $save->handle($this->year, SchoolLevel::Primary, AssessmentMode::Descriptive, schemeParts(['ignored' => 5]));

        expect($scheme->mode)->toBe(AssessmentMode::Descriptive)
            ->and(AssessmentComponent::count())->toBe(0);
    });

    it('cannot reach a part of another scheme', function () {
        $save = app(SaveAssessmentScheme::class);
        $other = $save->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, schemeParts(['Exam' => 100]))->components->sole();

        $save->handle($this->year, SchoolLevel::Primary, AssessmentMode::Weighted, [['id' => $other->id, 'name' => 'Exam', 'kind' => 'exam', 'weight' => 100]]);
    })->throws(ModelNotFoundException::class);
});

describe('copying and deleting years', function () {
    beforeEach(function () {
        $this->next = AcademicYear::factory()->for($this->school)->create(['name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-07-31']);
    });

    it('copies last year into a new year, skipping levels already set up there', function () {
        app(SetUpDefaultSchemes::class)->handle($this->year);
        app(SaveAssessmentScheme::class)->handle($this->year, SchoolLevel::Jhs, AssessmentMode::Weighted, schemeParts(['Tests' => 40, 'Exam' => 60]));
        app(SaveAssessmentScheme::class)->handle($this->next, SchoolLevel::Kg, AssessmentMode::Weighted, schemeParts(['Exam' => 100]));

        expect(app(CopySchemesFromYear::class)->handle($this->year, $this->next))->toBe(2)
            ->and(schemeWeights($this->next, SchoolLevel::Jhs))->toBe(['Tests' => 40, 'Exam' => 60])
            ->and(schemeWeights($this->next, SchoolLevel::Kg))->toBe(['Exam' => 100])
            ->and(schemeWeights($this->year, SchoolLevel::Jhs))->toBe(['Tests' => 40, 'Exam' => 60]);
    });

    it('skips levels the school no longer offers', function () {
        app(SetUpDefaultSchemes::class)->handle($this->year);
        $this->school->update(['levels_offered' => [SchoolLevel::Primary]]);

        expect(app(CopySchemesFromYear::class)->handle($this->year, $this->next))->toBe(1)
            ->and(AssessmentScheme::where('academic_year_id', $this->next->id)->pluck('level')->all())->toBe([SchoolLevel::Primary]);
    });

    it('removes the schemes of a deleted year', function () {
        app(SetUpDefaultSchemes::class)->handle($this->next);
        app(DeleteAcademicYear::class)->handle($this->next);

        expect(AssessmentScheme::count())->toBe(0)->and(AssessmentComponent::count())->toBe(0);
    });
});

describe('the screen', function () {
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
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/settings/assessment')->assertForbidden();
    });

    it('shows the empty state, then the standard setup', function () {
        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/assessment')
            ->assertOk()->assertSee('No assessment set up for 2026/2027')->assertSee('Use the standard setup');

        Livewire::actingAs($this->admin)->test(Schemes::class)
            ->call('setUpDefaults')
            ->assertSee('Continuous assessment 30%, exam 70%')
            ->assertSee('Teachers write a comment on each child')
            ->assertDontSee('No assessment set up');
    });

    it('edits a level and shows the running total', function () {
        app(SetUpDefaultSchemes::class)->handle($this->year);

        $component = Livewire::actingAs($this->admin)->test(Schemes::class)
            ->call('edit', 'primary')
            ->assertSet('mode', 'weighted')
            ->assertCount('parts', 4)
            ->call('removePart', 2)
            ->assertSee('Total 90% (needs 100%)')
            ->call('save')
            ->assertHasErrors('parts')
            ->set('parts.2.weight', '80')
            ->assertSee('Total 100%')
            ->call('save')
            ->assertHasNoErrors();

        expect(schemeWeights($this->year, SchoolLevel::Primary))->toBe(['Class exercises' => 10, 'Class tests' => 10, 'End of term exam' => 80]);

        $component->call('edit', 'kg')->assertSet('mode', 'descriptive')->set('mode', 'weighted')
            ->call('addPart')->assertCount('parts', 5);
    });

    it('copies from the previous year', function () {
        app(SetUpDefaultSchemes::class)->handle($this->year);
        $next = AcademicYear::factory()->for($this->school)->create(['name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-07-31']);

        Livewire::actingAs($this->admin)->withQueryParams(['year' => $next->id])->test(Schemes::class)
            ->assertSee('Copy from 2026/2027')
            ->call('copyFromPrevious')
            ->assertDontSee('Copy from 2026/2027');

        expect(AssessmentScheme::where('academic_year_id', $next->id)->count())->toBe(3);
    });

    it('asks for an academic year first', function () {
        $this->year->delete();

        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/assessment')
            ->assertOk()->assertSee('Create an academic year first');
    });

    it('is listed under School setup', function () {
        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/school')->assertSee('settings/assessment');
    });
});
