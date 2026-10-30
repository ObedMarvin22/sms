<?php

use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\DeleteClass;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Classes\SetClassSubjects;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Subjects\CreateSubject;
use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Classes\Subjects;
use App\Models\Activity;
use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Support\Academic\DefaultSubjects;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Jhs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
});

function klass(string $name): SchoolClass
{
    return SchoolClass::where('name', $name)->sole();
}

/**
 * @return list<string>
 */
function subjectsOf(string $class): array
{
    return klass($class)->subjects()->pluck('name')->all();
}

it("gives every class all of its level's subjects, once", function () {
    $created = app(ApplyDefaultClassSubjects::class)->handle();

    $kg = count(DefaultSubjects::for(SchoolLevel::Kg));
    $jhs = count(DefaultSubjects::for(SchoolLevel::Jhs));
    expect($created)->toBe(2 * $kg + 3 * $jhs)
        ->and(subjectsOf('JHS 2'))->toEqualCanonicalizing(DefaultSubjects::for(SchoolLevel::Jhs))
        ->and(subjectsOf('KG 1'))->toEqualCanonicalizing(DefaultSubjects::for(SchoolLevel::Kg));

    expect(app(ApplyDefaultClassSubjects::class)->handle())->toBe(0);
});

it('does not take subjects away when defaults are applied again', function () {
    app(ApplyDefaultClassSubjects::class)->handle();
    $keep = Subject::where('name', 'French')->sole()->id;
    app(SetClassSubjects::class)->handle(klass('JHS 1'), [$keep]);

    app(ApplyDefaultClassSubjects::class)->handle();

    expect(subjectsOf('JHS 1'))->toHaveCount(count(DefaultSubjects::for(SchoolLevel::Jhs)));
});

it("sets a class's subjects and logs what changed", function () {
    $math = Subject::where('name', 'Mathematics')->sole();
    $french = Subject::where('name', 'French')->sole();
    $science = Subject::where('name', 'Science')->sole();

    app(SetClassSubjects::class)->handle(klass('JHS 2'), [$math->id, $french->id]);
    app(SetClassSubjects::class)->handle(klass('JHS 2'), [$math->id, $science->id]);

    expect(subjectsOf('JHS 2'))->toBe(['Mathematics', 'Science']);

    $entry = Activity::where('event', 'class_subjects_set')->latest('id')->firstOrFail();
    expect($entry->properties['added'])->toBe(['Science'])
        ->and($entry->properties['removed'])->toBe(['French'])
        ->and($entry->school_id)->toBe($this->school->id);
});

it("refuses a subject that is not taught at the class's level", function () {
    $numeracy = Subject::where('name', 'Numeracy')->sole(); // KG only

    expect(fn () => app(SetClassSubjects::class)->handle(klass('JHS 1'), [$numeracy->id]))
        ->toThrow(ValidationException::class, 'JHS level');
});

it("refuses another school's subject", function () {
    $other = School::factory()->withLevels(SchoolLevel::Jhs)->create();
    $foreign = app(CurrentSchool::class)->runFor($other, fn () => app(CreateSubject::class)->handle('Foreign Maths', null, ['jhs']));

    expect(fn () => app(SetClassSubjects::class)->handle(klass('JHS 1'), [$foreign->id]))
        ->toThrow(ValidationException::class);
});

it('removes links when a class or subject is deleted', function () {
    app(ApplyDefaultClassSubjects::class)->handle();
    $before = ClassSubject::count();

    app(DeleteSubject::class)->handle(Subject::where('name', 'French')->sole());
    expect(ClassSubject::count())->toBe($before - 3); // three JHS classes

    // JHS 3 still has every JHS subject except the deleted French.
    $jhs3Links = count(DefaultSubjects::for(SchoolLevel::Jhs)) - 1;
    app(DeleteClass::class)->handle(klass('JHS 3'));
    expect(ClassSubject::count())->toBe($before - 3 - $jhs3Links);
});

describe('screen', function () {
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
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/settings/class-subjects')->assertForbidden();
        Livewire::actingAs($this->teacher)->test(Subjects::class)->assertForbidden();
    });

    it('lists classes and only the selected level\'s subjects, with a sidebar link', function () {
        $this->actingAs($this->admin)
            ->get('http://greenhill.localhost/settings/class-subjects?class='.klass('JHS 1')->id)
            ->assertOk()
            ->assertSee('Class subjects')
            ->assertSee('Career Technology')
            ->assertDontSee('Numeracy');
    });

    it('saves the ticked subjects for a class', function () {
        $math = Subject::where('name', 'Mathematics')->sole();

        Livewire::actingAs($this->admin)->test(Subjects::class)
            ->call('selectClass', klass('JHS 2')->id)
            ->set('selected', [(string) $math->id])
            ->call('save')
            ->assertHasNoErrors();

        expect(subjectsOf('JHS 2'))->toBe(['Mathematics']);
    });

    it('applies defaults from the screen', function () {
        Livewire::actingAs($this->admin)->test(Subjects::class)->call('applyDefaults');

        expect(subjectsOf('KG 2'))->toHaveCount(count(DefaultSubjects::for(SchoolLevel::Kg)));
    });
});
