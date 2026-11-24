<?php

use App\Actions\Grading\SaveGradeScale;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Grading\Scales;
use App\Models\Activity;
use App\Models\GradeBand;
use App\Models\GradeScale;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs, SchoolLevel::Shs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
});

function scaleOf(SchoolLevel $level): GradeScale
{
    return GradeScale::with('bands')->where('level', $level)->sole();
}

describe('the standard scales', function () {
    it('sets up NaCCA levels for Primary, 1–9 for JHS and A1–F9 for SHS, and nothing for KG', function () {
        expect(app(SetUpDefaultGradeScales::class)->handle())->toBe(3)
            ->and(GradeScale::where('level', SchoolLevel::Kg)->exists())->toBeFalse()
            ->and(scaleOf(SchoolLevel::Primary)->bands->pluck('grade')->all())->toBe(['HP', 'P', 'AP', 'D', 'E'])
            ->and(scaleOf(SchoolLevel::Jhs)->bands->pluck('points')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9])
            ->and(scaleOf(SchoolLevel::Shs)->bands->pluck('grade')->all())->toBe(['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'])
            ->and(app(SetUpDefaultGradeScales::class)->handle())->toBe(0);
    });

    it('grades totals at the band edges, rounding to a whole number first', function (SchoolLevel $level, float $total, string $grade) {
        app(SetUpDefaultGradeScales::class)->handle();

        expect(scaleOf($level)->bandFor($total)?->grade)->toBe($grade);
    })->with([
        'JHS 100' => [SchoolLevel::Jhs, 100.0, '1'],
        'JHS 80' => [SchoolLevel::Jhs, 80.0, '1'],
        'JHS 79.5 rounds up' => [SchoolLevel::Jhs, 79.5, '1'],
        'JHS 79.49 stays' => [SchoolLevel::Jhs, 79.49, '2'],
        'JHS 39.9 rounds to 40' => [SchoolLevel::Jhs, 39.9, '8'],
        'JHS 0' => [SchoolLevel::Jhs, 0.0, '9'],
        'SHS 64' => [SchoolLevel::Shs, 64.0, 'C4'],
        'SHS 65' => [SchoolLevel::Shs, 65.0, 'B3'],
        'Primary 68' => [SchoolLevel::Primary, 68.0, 'P'],
        'Primary 67.4' => [SchoolLevel::Primary, 67.4, 'AP'],
    ]);
});

describe('saving a scale', function () {
    it('saves bands highest first and logs the change', function () {
        $scale = app(SaveGradeScale::class)->handle(SchoolLevel::Primary, ' Our  grades ', [
            ['min_score' => '0', 'grade' => 'f', 'label' => 'Fail'],
            ['min_score' => 50, 'grade' => 'P', 'label' => 'Pass'],
            ['min_score' => 75, 'grade' => 'd', 'label' => 'Distinction'],
        ]);

        expect($scale->name)->toBe('Our grades')
            ->and($scale->bands->pluck('grade')->all())->toBe(['D', 'P', 'F'])
            ->and(Activity::where('log_name', 'grades')->where('subject_type', $scale->getMorphClass())->exists())->toBeTrue();
    });

    it('refuses bands that leave totals without a grade or clash', function (array $bands, string $message) {
        expect(fn () => app(SaveGradeScale::class)->handle(SchoolLevel::Primary, 'Grades', $bands))->toThrow(ValidationException::class, $message);
    })->with([
        'no band at 0' => [[['min_score' => 50, 'grade' => 'P', 'label' => 'Pass'], ['min_score' => 1, 'grade' => 'F', 'label' => 'Fail']], 'start at 0'],
        'same start twice' => [[['min_score' => 0, 'grade' => 'F', 'label' => 'Fail'], ['min_score' => 0, 'grade' => 'P', 'label' => 'Pass']], 'same score'],
        'same grade twice' => [[['min_score' => 0, 'grade' => 'F', 'label' => 'Fail'], ['min_score' => 50, 'grade' => 'f', 'label' => 'Pass']], 'same grade'],
        'over 100' => [[['min_score' => 0, 'grade' => 'F', 'label' => 'Fail'], ['min_score' => 101, 'grade' => 'P', 'label' => 'Pass']], '0 to 100'],
        'only one band' => [[['min_score' => 0, 'grade' => 'P', 'label' => 'Pass']], 'between 2'],
    ]);

    it('needs points for JHS and SHS, for the aggregate', function () {
        app(SaveGradeScale::class)->handle(SchoolLevel::Jhs, 'Grades', [
            ['min_score' => 0, 'grade' => '9', 'label' => 'Lowest', 'points' => 9],
            ['min_score' => 50, 'grade' => '1', 'label' => 'Highest', 'points' => ''],
        ]);
    })->throws(ValidationException::class, 'need points for the aggregate');

    it('replaces the bands of an existing scale', function () {
        app(SetUpDefaultGradeScales::class)->handle();
        app(SaveGradeScale::class)->handle(SchoolLevel::Primary, 'Simple', [
            ['min_score' => 0, 'grade' => 'F', 'label' => 'Fail'], ['min_score' => 50, 'grade' => 'P', 'label' => 'Pass'],
        ]);

        expect(GradeScale::where('level', SchoolLevel::Primary)->count())->toBe(1)
            ->and(scaleOf(SchoolLevel::Primary)->bands)->toHaveCount(2)
            ->and(GradeBand::count())->toBe(2 + 9 + 9);
    });

    it('refuses a level the school does not offer', function () {
        app(SaveGradeScale::class)->handle(SchoolLevel::Creche, 'Grades', [
            ['min_score' => 0, 'grade' => 'F', 'label' => 'Fail'], ['min_score' => 50, 'grade' => 'P', 'label' => 'Pass'],
        ]);
    })->throws(ValidationException::class, 'Choose a level the school offers.');
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

    it('is for staff who manage settings', function () {
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/settings/grading')->assertForbidden();
        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/grading')->assertOk()
            ->assertSee('Use the standard scales')->assertSee('Comments only, no grades');
    });

    it('sets up the standard scales and edits one', function () {
        Livewire::actingAs($this->admin)->test(Scales::class)
            ->call('setUpDefaults')
            ->assertSee('Grades 1 to 9 (BECE style)')
            ->call('edit', 'primary')
            ->assertCount('bands', 5)
            ->call('removeBand', 4)
            ->call('save')
            ->assertHasErrors('bands')
            ->call('addBand')
            ->set('bands.4', ['min_score' => '0', 'grade' => 'E', 'label' => 'Emerging', 'points' => null])
            ->call('save')
            ->assertHasNoErrors();

        expect(scaleOf(SchoolLevel::Primary)->bands)->toHaveCount(5);
    });
});
