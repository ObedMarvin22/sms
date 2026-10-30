<?php

use App\Actions\Classes\AddStream;
use App\Actions\Classes\CreateClass;
use App\Actions\Classes\DeleteClass;
use App\Actions\Classes\DeleteStream;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Classes\RenameClass;
use App\Actions\Classes\RenameStream;
use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Classes\Index;
use App\Models\Activity;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs)
        ->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
});

/**
 * @return list<string>
 */
function classNames(): array
{
    return SchoolClass::orderBy('sequence')->pluck('name')->all();
}

it("generates the usual classes for the school's levels, each with stream A", function () {
    expect(app(GenerateDefaultClasses::class)->handle())->toBe(11);

    expect(classNames())->toBe([
        'KG 1', 'KG 2',
        'Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6',
        'JHS 1', 'JHS 2', 'JHS 3',
    ]);

    SchoolClass::with('streams')->get()->each(fn (SchoolClass $class) => expect($class->streams->pluck('name')->all())->toBe(['A']));
    expect(Stream::with('schoolClass')->get()->map->full_name->contains('JHS 2A'))->toBeTrue();
});

it('generates classes for every level', function (SchoolLevel $level, array $names) {
    $this->school->update(['levels_offered' => [$level]]);

    app(GenerateDefaultClasses::class)->handle();

    expect(classNames())->toBe($names);
})->with([
    [SchoolLevel::Creche, ['Creche']],
    [SchoolLevel::Nursery, ['Nursery 1', 'Nursery 2']],
    [SchoolLevel::Shs, ['SHS 1', 'SHS 2', 'SHS 3']],
]);

it('only adds missing classes when run again, and never renames or deletes', function () {
    $generate = app(GenerateDefaultClasses::class);
    $generate->handle();
    app(RenameClass::class)->handle(SchoolClass::where('name', 'KG 1')->sole(), 'Kindergarten 1');
    SchoolClass::where('name', 'JHS 3')->sole()->delete();

    expect($generate->handle())->toBe(2); // KG 1 (renamed away) and JHS 3 come back

    expect(classNames())->toContain('Kindergarten 1', 'KG 1', 'JHS 3')
        ->and(SchoolClass::count())->toBe(12);
});

it('adds a class at the end of its level with stream A', function () {
    app(GenerateDefaultClasses::class)->handle();

    $class = app(CreateClass::class)->handle(SchoolLevel::Primary, '  Primary   7 ');

    expect($class->name)->toBe('Primary 7')
        ->and($class->streams->pluck('name')->all())->toBe(['A'])
        ->and(array_slice(classNames(), 2, 7))->toBe(['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6', 'Primary 7']);
});

it('refuses a class for a level the school does not offer', function () {
    expect(fn () => app(CreateClass::class)->handle(SchoolLevel::Shs, 'SHS 1'))
        ->toThrow(ValidationException::class, 'does not offer');
});

it('keeps class names unique in the school, ignoring case', function () {
    app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 1');

    expect(fn () => app(CreateClass::class)->handle(SchoolLevel::Jhs, 'jhs 1'))->toThrow(ValidationException::class, 'already');
    expect(fn () => app(RenameClass::class)->handle(app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 2'), 'JHS 1'))
        ->toThrow(ValidationException::class);
});

it('allows the same class name in another school', function () {
    app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 1');

    $other = School::factory()->withLevels(SchoolLevel::Jhs)->create();
    app(CurrentSchool::class)->runFor($other, fn () => app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 1'));

    expect(SchoolClass::withoutGlobalScopes()->where('name', 'JHS 1')->count())->toBe(2);
});

it('adds, renames and removes streams', function () {
    $class = app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 2');

    $b = app(AddStream::class)->handle($class, ' b ');
    expect($b->name)->toBe('B');

    expect(fn () => app(AddStream::class)->handle($class, 'A'))->toThrow(ValidationException::class, 'already has');

    app(RenameStream::class)->handle($b, 'Gold');
    expect($b->fresh()->name)->toBe('GOLD');

    app(DeleteStream::class)->handle($b->fresh());
    expect($class->streams()->pluck('name')->all())->toBe(['A']);
});

it('keeps at least one stream per class', function () {
    $class = app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 2');

    expect(fn () => app(DeleteStream::class)->handle($class->streams()->sole()))
        ->toThrow(ValidationException::class, 'at least one stream');
});

it('deletes a class with its streams', function () {
    $class = app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 2');
    app(AddStream::class)->handle($class, 'B');

    app(DeleteClass::class)->handle($class);

    expect(SchoolClass::count())->toBe(0)->and(Stream::count())->toBe(0);
});

it('logs class and stream changes', function () {
    $class = app(CreateClass::class)->handle(SchoolLevel::Jhs, 'JHS 2');
    app(RenameClass::class)->handle($class, 'JHS Two');

    $entry = Activity::where('log_name', 'classes')->where('subject_id', $class->id)->latest('id')->firstOrFail();

    expect($entry->attribute_changes['old']['name'])->toBe('JHS 2')
        ->and($entry->attribute_changes['attributes']['name'])->toBe('JHS Two')
        ->and($entry->school_id)->toBe($this->school->id);
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
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/settings/classes')->assertForbidden();
        Livewire::actingAs($this->teacher)->test(Index::class)->assertForbidden();
    });

    it('lists classes with streams for administrators and links from the sidebar', function () {
        app(GenerateDefaultClasses::class)->handle();

        $this->actingAs($this->admin)
            ->get('http://greenhill.localhost/settings/classes')
            ->assertOk()
            ->assertSee('Primary 6')
            ->assertSee('JHS 2A')
            ->assertSee('Classes');
    });

    it('generates, adds a class and adds a stream from the screen', function () {
        $component = Livewire::actingAs($this->admin)->test(Index::class)
            ->call('generateDefaults')
            ->set('newLevel', 'jhs')
            ->set('newName', 'JHS 4')
            ->call('createClass')
            ->assertHasNoErrors();

        $jhs2 = SchoolClass::where('name', 'JHS 2')->sole();
        $component->set("newStream.{$jhs2->id}", 'B')->call('addStream', $jhs2->id)->assertHasNoErrors();

        expect(SchoolClass::where('name', 'JHS 4')->exists())->toBeTrue()
            ->and($jhs2->streams()->pluck('name')->all())->toBe(['A', 'B']);
    });

    it('shows Action errors on the form', function () {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('newLevel', 'jhs')
            ->set('newName', '')
            ->call('createClass')
            ->assertHasErrors('new');
    });
});
