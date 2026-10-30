<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\GenerateAdmissionNumber;
use App\Actions\Students\SetAdmissionFormat;
use App\Actions\Students\UpdateStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\School\Profile;
use App\Livewire\Students\Index;
use App\Models\Activity;
use App\Models\School;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)
        ->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    readyForStudents();
});

/**
 * A current 2026/2027 year and the default classes, so students can be admitted.
 */
function readyForStudents(): void
{
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
}

function admit(array $input = []): Student
{
    $stream = Stream::query()->orderBy('id')->firstOrFail();

    return app(AdmitStudent::class)->handle(array_merge(['first_name' => 'Abena', 'last_name' => 'Mensah', 'stream_id' => $stream->id], $input));
}

it('numbers students in the default format, one after another', function () {
    $year = now()->year;

    expect(admit()->admission_number)->toBe("GA/{$year}/0001")
        ->and(admit()->admission_number)->toBe("GA/{$year}/0002")
        ->and(Student::where('admission_number', "GA/{$year}/0001")->sole()->status)->toBe(StudentStatus::Active);
});

it('restarts numbering in a new year', function () {
    admit(['admitted_on' => '2026-09-08']);
    admit(['admitted_on' => '2026-09-09']);

    expect(admit(['admitted_on' => '2027-01-12'])->admission_number)->toBe('GA/2027/0001');
});

it('keeps one sequence when the format has no year', function () {
    app(SetAdmissionFormat::class)->handle($this->school, 'gha', '{PREFIX}-{SEQ:5}');

    admit(['admitted_on' => '2026-09-08']);

    expect(admit(['admitted_on' => '2027-01-12'])->admission_number)->toBe('GHA-00002');
});

it('keeps a typed-in number and skips it when generating later', function () {
    $year = now()->year;
    admit(['admission_number' => "GA/{$year}/0002"]);

    expect(admit()->admission_number)->toBe("GA/{$year}/0001")
        ->and(admit()->admission_number)->toBe("GA/{$year}/0003");
});

it('refuses a duplicate admission number, even of a deleted student', function () {
    admit(['admission_number' => 'OLD-1'])->delete();

    admit(['admission_number' => 'OLD-1']);
})->throws(ValidationException::class, 'Another student');

it('allows the same admission number in another school', function () {
    admit(['admission_number' => 'SAME-1']);
    $other = School::factory()->create();

    $theirs = app(CurrentSchool::class)->runFor($other, function () {
        readyForStudents();

        return admit(['admission_number' => 'SAME-1']);
    });

    expect($theirs->admission_number)->toBe('SAME-1');
});

it('rejects invalid student details', function (array $input, string $key) {
    try {
        admit($input);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }
})->with([
    'no first name' => [['first_name' => ''], 'first_name'],
    'bad gender' => [['gender' => 'x'], 'gender'],
    'birth in the future' => [['date_of_birth' => CarbonImmutable::tomorrow()->toDateString()], 'date_of_birth'],
    'not a date' => [['admitted_on' => '2026-13-01'], 'admitted_on'],
]);

it('edits a student but keeps an admission number', function () {
    $student = admit();

    app(UpdateStudent::class)->handle($student, ['admission_number' => 'FIXED-1', 'first_name' => 'Abena', 'last_name' => 'Owusu', 'admitted_on' => $student->admitted_on->toDateString()]);
    expect($student->fresh())->last_name->toBe('Owusu')->admission_number->toBe('FIXED-1');

    expect(fn () => app(UpdateStudent::class)->handle($student->fresh(), ['admission_number' => '', 'first_name' => 'A', 'last_name' => 'B']))
        ->toThrow(ValidationException::class, 'keep an admission number');

    expect(Activity::where('log_name', 'students')->where('subject_id', $student->id)->count())->toBeGreaterThanOrEqual(2);
});

it('validates and saves the admission format', function () {
    expect(fn () => app(SetAdmissionFormat::class)->handle($this->school, 'GHS', '{PREFIX}/{YYYY}'))->toThrow(ValidationException::class);
    expect(fn () => app(SetAdmissionFormat::class)->handle($this->school, 'G H S', '{SEQ:4}'))->toThrow(ValidationException::class);

    app(SetAdmissionFormat::class)->handle($this->school, 'ghs', '{PREFIX}/{YY}/{SEQ:3}');

    expect(app(GenerateAdmissionNumber::class)->handle(CarbonImmutable::parse('2026-09-01')))->toBe('GHS/26/001');
});

describe('screens', function () {
    beforeEach(function () {
        app(SeedDefaultRoles::class)->handle($this->school);

        $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
        $this->admin->assignRole(RoleKey::Administrator->value);
        $this->teacher = User::factory()->for($this->school)->create();
        $this->teacher->assignRole(RoleKey::ClassTeacher->value);
        $this->gate = User::factory()->for($this->school)->create();
        $this->gate->assignRole(RoleKey::GateStaff->value);
        $this->onSchool($this->school);
    });

    it('lets teachers view students but not admit them', function () {
        admit(['first_name' => 'Kofi']);

        $this->actingAs($this->teacher)->get('http://greenhill.localhost/students')
            ->assertOk()->assertSee('Kofi Mensah')->assertDontSee('Admit student');

        Livewire::actingAs($this->teacher)->test(Index::class)->call('startCreate')->assertForbidden();
    });

    it('refuses staff without students.view', function () {
        $this->actingAs($this->gate)->get('http://greenhill.localhost/students')->assertForbidden();
    });

    it('admits a student from the screen with a generated number', function () {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startCreate')
            ->set('form.first_name', 'Esi')
            ->set('form.last_name', 'Asante')
            ->set('form.stream_id', (string) Stream::query()->orderBy('id')->value('id'))
            ->call('save')
            ->assertHasNoErrors();

        expect(Student::sole()->admission_number)->toBe('GA/'.now()->year.'/0001');
    });

    it('sends schools that are not ready to the setup checklist', function () {
        app(CurrentSchool::class)->runFor($this->school, fn () => Term::query()->get()->each(fn ($t) => $t->forceFill(['is_current' => false])->save()));

        $this->actingAs($this->admin)->get('http://greenhill.localhost/students')
            ->assertRedirect('http://greenhill.localhost/setup');
    });

    it('edits the admission format on School profile with a preview', function () {
        Livewire::actingAs($this->admin)->test(Profile::class)
            ->set('admission_prefix', 'ghs')
            ->set('admission_number_format', '{PREFIX}-{SEQ:5}')
            ->assertSee('GHS-00001')
            ->call('saveAdmissionFormat')
            ->assertHasNoErrors();

        expect(admit()->admission_number)->toBe('GHS-00001');
    });
});
