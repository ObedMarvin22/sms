<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Subjects\CreateProgramme;
use App\Actions\Subjects\CreateSubject;
use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\GenerateDefaultProgrammes;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Actions\Subjects\SetProgrammeSubjects;
use App\Actions\Subjects\UpdateSubject;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\SubjectType;
use App\Livewire\Subjects\Index;
use App\Livewire\Subjects\Programmes;
use App\Models\Activity;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Support\Academic\DefaultSubjects;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary, SchoolLevel::Jhs, SchoolLevel::Shs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
});

function subject(string $name): Subject
{
    return Subject::where('name', $name)->sole();
}

/**
 * @return array{core: list<string>, elective: list<string>}
 */
function programmeSubjects(Programme $programme): array
{
    $programme->load('subjects');

    return [
        'core' => $programme->subjects->filter(fn ($s) => $s->pivot->type === SubjectType::Core)->pluck('name')->sort()->values()->all(),
        'elective' => $programme->subjects->filter(fn ($s) => $s->pivot->type === SubjectType::Elective)->pluck('name')->sort()->values()->all(),
    ];
}

it('creates preset subjects, merging names taught at several levels', function () {
    app(GenerateDefaultSubjects::class)->handle();

    expect(subject('English Language')->levels->all())->toBe([SchoolLevel::Primary, SchoolLevel::Jhs, SchoolLevel::Shs])
        ->and(subject('Our World Our People')->levels->all())->toBe([SchoolLevel::Primary])
        ->and(subject('Social Studies')->levels->all())->toBe([SchoolLevel::Jhs, SchoolLevel::Shs])
        ->and(subject('Integrated Science')->code)->toBe('ISCI')
        ->and(Subject::where('name', 'Numeracy')->exists())->toBeFalse(); // KG not offered
});

it('adds nothing new when run again, but fills in levels added later', function () {
    $generate = app(GenerateDefaultSubjects::class);
    $generate->handle();
    $count = Subject::count();

    expect($generate->handle())->toBe(0)->and(Subject::count())->toBe($count);

    $this->school->update(['levels_offered' => [...$this->school->levels_offered->all(), SchoolLevel::Kg]]);
    $generate->handle();

    expect(subject('Creative Arts')->levels->all())->toContain(SchoolLevel::Kg, SchoolLevel::Primary)
        ->and(Subject::where('name', 'Numeracy')->exists())->toBeTrue();
});

it('creates and updates a subject with validated name, code and levels', function () {
    $subject = app(CreateSubject::class)->handle('  Robotics ', 'rob', ['jhs']);

    expect($subject->name)->toBe('Robotics')->and($subject->code)->toBe('ROB');

    app(UpdateSubject::class)->handle($subject, 'Robotics and Coding', '', ['jhs', 'shs']);

    expect($subject->fresh())
        ->name->toBe('Robotics and Coding')
        ->code->toBeNull()
        ->levels->all()->toBe([SchoolLevel::Jhs, SchoolLevel::Shs]);
});

it('rejects invalid subjects', function (array $input, string $key) {
    app(CreateSubject::class)->handle('Mathematics', 'MATH', ['primary']);

    try {
        app(CreateSubject::class)->handle(...$input);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }
})->with([
    'duplicate name, any case' => [['mathematics', null, ['jhs']], 'name'],
    'duplicate code' => [['Further Maths', 'math', ['shs']], 'code'],
    'bad code' => [['Art', 'A RT!', ['jhs']], 'code'],
    'no levels' => [['Art', null, []], 'levels'],
    'level not offered' => [['Numeracy', null, ['kg']], 'levels'],
    'blank name' => [['  ', null, ['jhs']], 'name'],
]);

it('creates the preset programmes with core and electives', function () {
    expect(app(GenerateDefaultProgrammes::class)->handle())->toBe(count(DefaultSubjects::PROGRAMMES));

    expect(programmeSubjects(Programme::where('name', 'General Science')->sole()))->toBe([
        'core' => ['Core Mathematics', 'English Language', 'Integrated Science', 'Social Studies'],
        'elective' => ['Biology', 'Chemistry', 'Elective Mathematics', 'Physics'],
    ]);
    expect(app(GenerateDefaultProgrammes::class)->handle())->toBe(0);
});

it('stamps programme links with the school', function () {
    app(GenerateDefaultProgrammes::class)->handle();

    expect(ProgrammeSubject::withoutGlobalScopes()->distinct()->pluck('school_id')->all())->toBe([$this->school->id]);
});

it('only accepts SHS subjects of this school, never core and elective at once', function () {
    app(GenerateDefaultSubjects::class)->handle();
    $programme = app(CreateProgramme::class)->handle('Technical');
    $set = app(SetProgrammeSubjects::class);

    expect(fn () => $set->handle($programme, [subject('English Language')->id], [subject('English Language')->id]))
        ->toThrow(ValidationException::class, 'both');
    expect(fn () => $set->handle($programme, [], [subject('Our World Our People')->id]))
        ->toThrow(ValidationException::class, 'SHS subjects');

    $other = School::factory()->withLevels(SchoolLevel::Shs)->create();
    $foreign = app(CurrentSchool::class)->runFor($other, fn () => app(CreateSubject::class)->handle('Foreign', null, ['shs']));
    expect(fn () => $set->handle($programme, [], [$foreign->id]))->toThrow(ValidationException::class);

    $set->handle($programme, [subject('English Language')->id], [subject('Physics')->id]);
    expect(programmeSubjects($programme))->toBe(['core' => ['English Language'], 'elective' => ['Physics']]);

    $entry = Activity::where('event', 'programme_subjects_set')->latest('id')->firstOrFail();
    expect($entry->properties['electives'])->toBe(['Physics']);
});

it('refuses programmes for a school without SHS', function () {
    $this->school->update(['levels_offered' => [SchoolLevel::Primary]]);

    expect(fn () => app(CreateProgramme::class)->handle('General Arts'))->toThrow(ValidationException::class, 'offer SHS');
    expect(fn () => app(GenerateDefaultProgrammes::class)->handle())->toThrow(ValidationException::class);
});

it('removes a deleted subject from programmes', function () {
    app(GenerateDefaultProgrammes::class)->handle();

    app(DeleteSubject::class)->handle(subject('Physics'));

    expect(programmeSubjects(Programme::where('name', 'General Science')->sole())['elective'])
        ->toBe(['Biology', 'Chemistry', 'Elective Mathematics']);
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
    })->with(['/settings/subjects', '/settings/programmes']);

    it('lists subjects, filters by level and links to programmes for SHS schools', function () {
        app(GenerateDefaultSubjects::class)->handle();

        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/subjects')
            ->assertOk()->assertSee('English Language')->assertSee('SHS programmes');

        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('level', 'primary')
            ->assertSee('Our World Our People')
            ->assertDontSee('Integrated Science');
    });

    it('creates a subject from the modal and shows Action errors', function () {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startCreate')
            ->set('name', 'Robotics')
            ->set('levels', ['jhs'])
            ->call('save')
            ->assertHasNoErrors()
            ->call('startCreate')
            ->set('name', 'robotics')
            ->set('levels', ['jhs'])
            ->call('save')
            ->assertHasErrors('name');
    });

    it('hides programmes from schools without SHS', function () {
        $this->school->update(['levels_offered' => [SchoolLevel::Primary]]);

        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/programmes')->assertNotFound();
        $this->actingAs($this->admin)->get('http://greenhill.localhost/settings/subjects')->assertDontSee('SHS programmes');
    });

    it('generates programmes and edits one from the screen', function () {
        $component = Livewire::actingAs($this->admin)->test(Programmes::class)->call('generateDefaults');
        $business = Programme::where('name', 'Business')->sole();

        $component->call('startEdit', $business->id)
            ->set('editName', 'Business Studies')
            ->set('electives', [(string) subject('Economics')->id])
            ->call('save')
            ->assertHasNoErrors();

        expect($business->fresh()->name)->toBe('Business Studies')
            ->and(programmeSubjects($business)['elective'])->toBe(['Economics'])
            ->and(programmeSubjects($business)['core'])->toHaveCount(4);
    });
});
