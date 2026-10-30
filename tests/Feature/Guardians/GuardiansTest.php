<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Guardians\FindGuardianByPhone;
use App\Actions\Guardians\LinkGuardian;
use App\Actions\Guardians\SetPrimaryGuardian;
use App\Actions\Guardians\UnlinkGuardian;
use App\Actions\Guardians\UpdateGuardian;
use App\Actions\Guardians\UpdateGuardianLink;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\GuardianAccess;
use App\Enums\GuardianRelationship;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Guardians\Index;
use App\Livewire\Students\Guardians;
use App\Models\Activity;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\School;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);

    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    $this->kofi = child('Kofi');
    $this->ama = child('Ama');
});

function child(string $firstName): Student
{
    return app(AdmitStudent::class)->handle([
        'first_name' => $firstName, 'last_name' => 'Mensah', 'stream_id' => Stream::query()->orderBy('id')->firstOrFail()->id,
    ]);
}

function addGuardian(Student $student, array $input = []): Guardian
{
    return app(AddGuardian::class)->handle($student, array_merge([
        'name' => 'Akosua Mensah', 'phone' => '024 412 3456', 'relationship' => 'mother',
    ], $input));
}

function linkOf(Guardian $guardian, Student $student): GuardianStudent
{
    return GuardianStudent::where('guardian_id', $guardian->id)->where('student_id', $student->id)->sole();
}

it('adds a guardian with a normalised phone as the primary contact', function () {
    $guardian = addGuardian($this->kofi, ['email' => 'Akosua@Example.com', 'alt_phone' => '0501234567']);

    $link = linkOf($guardian, $this->kofi);
    expect($guardian->phone)->toBe('+233244123456')
        ->and($guardian->alt_phone)->toBe('+233501234567')
        ->and($guardian->email)->toBe('akosua@example.com')
        ->and($link->relationship)->toBe(GuardianRelationship::Mother)
        ->and($link->access_level)->toBe(GuardianAccess::Full)
        ->and($link->is_primary)->toBeTrue()
        ->and($link->can_pick_up)->toBeTrue();
});

it('finds a guardian by phone however it is typed', function (string $typed) {
    $guardian = addGuardian($this->kofi);

    expect(app(FindGuardianByPhone::class)->handle($typed)?->id)->toBe($guardian->id);
})->with(['0244123456', '024 412 3456', '+233 24 412 3456', '233244123456', '00233-24-412-3456', '244123456']);

it('finds nobody for an unknown or invalid phone', function (?string $typed) {
    addGuardian($this->kofi);

    expect(app(FindGuardianByPhone::class)->handle($typed))->toBeNull();
})->with(['0244999999', 'not a phone', '', null]);

it('refuses a second guardian with the same phone, however it is typed', function () {
    addGuardian($this->kofi);

    expect(fn () => addGuardian($this->ama, ['name' => 'Someone Else', 'phone' => '+233 24 412 3456']))
        ->toThrow(ValidationException::class, 'Akosua Mensah already uses this number');

    expect(Guardian::count())->toBe(1);
});

it('links one guardian to brothers and sisters', function () {
    $guardian = addGuardian($this->kofi);

    app(LinkGuardian::class)->handle($this->ama, $guardian, ['relationship' => 'mother', 'access_level' => 'limited', 'can_pick_up' => false]);

    $link = linkOf($guardian, $this->ama);
    expect($guardian->links()->count())->toBe(2)
        ->and($link->is_primary)->toBeTrue()
        ->and($link->access_level)->toBe(GuardianAccess::Limited)
        ->and($link->can_pick_up)->toBeFalse();
});

it('refuses to link the same guardian to a child twice', function () {
    $guardian = addGuardian($this->kofi);

    expect(fn () => app(LinkGuardian::class)->handle($this->kofi, $guardian, ['relationship' => 'mother']))
        ->toThrow(ValidationException::class, 'already a guardian of this child');
});

it('rejects invalid guardian details', function (array $input, string $key) {
    try {
        addGuardian($this->kofi, $input);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }
})->with([
    'no name' => [['name' => ' '], 'name'],
    'no phone' => [['phone' => ''], 'phone'],
    'foreign phone' => [['phone' => '+44 7700 900123'], 'phone'],
    'bad other phone' => [['alt_phone' => '12345'], 'alt_phone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'no relationship' => [['relationship' => ''], 'relationship'],
    'unknown relationship' => [['relationship' => 'cousin'], 'relationship'],
    'unknown access' => [['access_level' => 'everything'], 'access_level'],
]);

it('saves nothing when the link details are invalid', function () {
    expect(fn () => addGuardian($this->kofi, ['relationship' => 'cousin']))->toThrow(ValidationException::class);

    expect(Guardian::count())->toBe(0);
});

it('keeps exactly one primary contact per child', function () {
    $mother = addGuardian($this->kofi);
    $father = addGuardian($this->kofi, ['name' => 'Kwame Mensah', 'phone' => '0201112222', 'relationship' => 'father']);

    expect(linkOf($father, $this->kofi)->is_primary)->toBeFalse();

    app(SetPrimaryGuardian::class)->handle(linkOf($father, $this->kofi));

    expect(linkOf($father, $this->kofi)->is_primary)->toBeTrue()
        ->and(linkOf($mother, $this->kofi)->is_primary)->toBeFalse()
        ->and(GuardianStudent::where('student_id', $this->kofi->id)->where('is_primary', true)->count())->toBe(1);
});

it('lets the database refuse a second primary contact', function () {
    addGuardian($this->kofi);
    $father = addGuardian($this->kofi, ['name' => 'Kwame Mensah', 'phone' => '0201112222', 'relationship' => 'father']);

    expect(fn () => linkOf($father, $this->kofi)->forceFill(['is_primary' => true])->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('never removes a child\'s last guardian', function () {
    $mother = addGuardian($this->kofi);

    expect(fn () => app(UnlinkGuardian::class)->handle(linkOf($mother, $this->kofi)))
        ->toThrow(ValidationException::class, 'at least one guardian');
});

it('passes the primary role on when the primary guardian is removed', function () {
    $mother = addGuardian($this->kofi);
    $father = addGuardian($this->kofi, ['name' => 'Kwame Mensah', 'phone' => '0201112222', 'relationship' => 'father']);

    app(UnlinkGuardian::class)->handle(linkOf($mother, $this->kofi));

    expect(linkOf($father, $this->kofi)->is_primary)->toBeTrue()
        ->and(Guardian::find($mother->id))->not->toBeNull();
});

it('edits a guardian and refuses another guardian\'s phone', function () {
    $mother = addGuardian($this->kofi);
    addGuardian($this->ama, ['name' => 'Kwame Mensah', 'phone' => '0201112222', 'relationship' => 'father']);

    app(UpdateGuardian::class)->handle($mother, ['name' => 'Akosua Owusu', 'phone' => '+233244123456']);
    expect($mother->fresh()->name)->toBe('Akosua Owusu');

    expect(fn () => app(UpdateGuardian::class)->handle($mother, ['name' => 'Akosua Owusu', 'phone' => '020 111 2222']))
        ->toThrow(ValidationException::class, 'Kwame Mensah already uses this number');
});

it('logs guardian and link changes', function () {
    $mother = addGuardian($this->kofi);
    app(UpdateGuardianLink::class)->handle(linkOf($mother, $this->kofi), ['relationship' => 'mother', 'can_pick_up' => false]);
    $father = addGuardian($this->kofi, ['name' => 'Kwame Mensah', 'phone' => '0201112222', 'relationship' => 'father']);
    app(SetPrimaryGuardian::class)->handle(linkOf($father, $this->kofi));
    app(UnlinkGuardian::class)->handle(linkOf($mother, $this->kofi));

    $events = Activity::where('log_name', 'guardians')->where('subject_type', (new GuardianStudent)->getMorphClass())
        ->orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['created', 'updated', 'created', 'updated', 'updated', 'deleted'])
        ->and(Activity::where('log_name', 'guardians')->where('subject_type', (new Guardian)->getMorphClass())->count())->toBe(2);

    $pickupChange = Activity::where('log_name', 'guardians')->where('event', 'updated')->orderBy('id')->firstOrFail();
    expect($pickupChange->attribute_changes['attributes'])->toBe(['can_pick_up' => false]);
});

it('allows the same phone in another school and keeps it there', function () {
    addGuardian($this->kofi);

    $other = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'hillview']);
    $theirs = app(CurrentSchool::class)->runFor($other, function () {
        $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
        app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
        app(GenerateDefaultClasses::class)->handle();

        expect(app(FindGuardianByPhone::class)->handle('0244123456'))->toBeNull();

        return addGuardian(child('Yaw'), ['name' => 'Akosua at Hillview']);
    });

    expect($theirs->phone)->toBe('+233244123456')
        ->and(Guardian::pluck('name')->all())->toBe(['Akosua Mensah']);
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

    it('shows guardians on the student page', function () {
        addGuardian($this->kofi);

        $this->actingAs($this->teacher)->get("http://greenhill.localhost/students/{$this->kofi->id}")
            ->assertOk()->assertSee('Akosua Mensah')->assertSee('+233244123456')->assertDontSee('Add guardian');
    });

    it('adds a guardian from the student page', function () {
        Livewire::actingAs($this->admin)->test(Guardians::class, ['studentId' => $this->kofi->id])
            ->call('startAdd')
            ->set('form.phone', '0244123456')
            ->assertSet('matchId', null)
            ->set('form.name', 'Akosua Mensah')
            ->set('form.relationship', 'mother')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Akosua Mensah');

        expect(Guardian::sole()->phone)->toBe('+233244123456');
    });

    it('offers to link a guardian already on file instead of creating a duplicate', function () {
        $mother = addGuardian($this->kofi);

        Livewire::actingAs($this->admin)->test(Guardians::class, ['studentId' => $this->ama->id])
            ->call('startAdd')
            ->set('form.phone', '+233 24 412 3456')
            ->assertSet('matchId', $mother->id)
            ->assertSee('Akosua Mensah already uses this number')
            ->set('form.relationship', 'mother')
            ->call('save')
            ->assertHasNoErrors();

        expect(Guardian::count())->toBe(1)
            ->and(linkOf($mother, $this->ama)->is_primary)->toBeTrue();
    });

    it('shows why the last guardian cannot be removed', function () {
        $mother = addGuardian($this->kofi);

        Livewire::actingAs($this->admin)->test(Guardians::class, ['studentId' => $this->kofi->id])
            ->call('unlink', linkOf($mother, $this->kofi)->id)
            ->assertHasErrors('guardian');
    });

    it('cannot touch another child\'s guardian link', function () {
        $mother = addGuardian($this->ama);

        Livewire::actingAs($this->admin)->test(Guardians::class, ['studentId' => $this->kofi->id])
            ->call('makePrimary', linkOf($mother, $this->ama)->id)
            ->assertNotFound();
    });

    it('lets teachers view guardians but not change them', function () {
        $mother = addGuardian($this->kofi);

        Livewire::actingAs($this->teacher)->test(Guardians::class, ['studentId' => $this->kofi->id])
            ->call('startAdd')->assertForbidden();
        Livewire::actingAs($this->teacher)->test(Guardians::class, ['studentId' => $this->kofi->id])
            ->call('unlink', linkOf($mother, $this->kofi)->id)->assertForbidden();
        Livewire::actingAs($this->teacher)->test(Index::class)
            ->call('startEdit', $mother->id)->assertForbidden();
    });

    it('lists and searches guardians by name or any phone format', function () {
        addGuardian($this->kofi);
        addGuardian($this->ama, ['name' => 'Kwame Boateng', 'phone' => '0201112222', 'relationship' => 'father']);

        $this->actingAs($this->teacher)->get('http://greenhill.localhost/guardians')
            ->assertOk()->assertSee('Akosua Mensah')->assertSee('Kwame Boateng');

        Livewire::actingAs($this->teacher)->test(Index::class)
            ->set('search', '020 111 2222')->assertSee('Kwame Boateng')->assertDontSee('Akosua Mensah')
            ->set('search', 'akosua')->assertSee('Akosua Mensah')->assertDontSee('Kwame Boateng');
    });

    it('edits a guardian from the list', function () {
        $mother = addGuardian($this->kofi);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startEdit', $mother->id)
            ->set('form.name', 'Akosua Owusu')
            ->call('save')
            ->assertHasNoErrors();

        expect($mother->fresh()->name)->toBe('Akosua Owusu');
    });

    it('refuses staff without students.view', function () {
        $this->actingAs($this->gate)->get('http://greenhill.localhost/guardians')->assertForbidden();
    });
});
