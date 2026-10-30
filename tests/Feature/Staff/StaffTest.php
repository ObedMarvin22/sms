<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Staff\AssignTeacher;
use App\Actions\Staff\CreateStaff;
use App\Actions\Staff\MarkStaffLeft;
use App\Actions\Staff\ResendInvitation;
use App\Actions\Staff\SetStaffRoles;
use App\Actions\Staff\UpdateStaff;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AcademicPeriodType;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\StaffStatus;
use App\Livewire\Staff\Index;
use App\Livewire\Staff\TeachingAssignments;
use App\Models\ClassTeacherAssignment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Jhs)->create(['subdomain' => 'greenhill']);
    app(SeedDefaultRoles::class)->handle($this->school);
    app(CurrentSchool::class)->set($this->school);

    $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->admin->assignRole(RoleKey::Administrator->value);
    $this->proprietor = User::factory()->for($this->school)->withTwoFactor()->create();
    $this->proprietor->assignRole(RoleKey::Proprietor->value);
});

function newStaff(array $input = [], array $roles = [], ?User $actor = null): Staff
{
    return app(CreateStaff::class)->handle(
        array_merge(['first_name' => 'Kwame', 'last_name' => 'Owusu'], $input),
        $roles,
        $actor ?? test()->proprietor,
    );
}

function setUpTeaching(): void
{
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
    app(GenerateDefaultSubjects::class)->handle();
    app(ApplyDefaultClassSubjects::class)->handle();
}

function jhs2A(): Stream
{
    return SchoolClass::where('name', 'JHS 2')->sole()->streams()->sole();
}

it('creates staff with a login, roles and an invitation when there is an email', function () {
    $staff = newStaff(['email' => ' Kwame@Greenhill.TEST ', 'phone' => '024 412 3456', 'gender' => 'male'], ['class_teacher']);

    expect($staff->user)->not->toBeNull()
        ->and($staff->email)->toBe('kwame@greenhill.test')
        ->and($staff->phone)->toBe('+233244123456')
        ->and($staff->user->school_id)->toBe($this->school->id)
        ->and($staff->user->hasRole('class_teacher'))->toBeTrue();

    Notification::assertSentTo($staff->user, SchoolInvitation::class);
});

it('creates a record without a login when there is no email', function () {
    $staff = newStaff(['job_title' => 'Cleaner']);

    expect($staff->user_id)->toBeNull();
    Notification::assertNothingSent();
});

it('requires an email for staff given a role', function () {
    newStaff([], ['class_teacher']);
})->throws(ValidationException::class, 'need an email');

it('rejects invalid staff details', function (array $input, string $key) {
    newStaff(['email' => 'taken@greenhill.test', 'staff_number' => 'GH-001']);

    try {
        newStaff($input);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }
})->with([
    'missing first name' => [['first_name' => ' '], 'first_name'],
    'bad phone' => [['phone' => '+44 20 7946 0958'], 'phone'],
    'bad email' => [['email' => 'nope'], 'email'],
    'email already used' => [['email' => 'TAKEN@greenhill.test'], 'email'],
    'staff number already used' => [['staff_number' => 'GH-001'], 'staff_number'],
    'not a date' => [['employed_on' => '2026-02-30'], 'employed_on'],
    'bad gender' => [['gender' => 'x'], 'gender'],
]);

it('gives a login and invitation when an email is added later', function () {
    $staff = newStaff();

    app(UpdateStaff::class)->handle($staff, ['first_name' => 'Kwame', 'last_name' => 'Owusu', 'email' => 'kwame@greenhill.test']);

    expect($staff->fresh()->user)->not->toBeNull();
    Notification::assertSentTo($staff->fresh()->user, SchoolInvitation::class);
});

it('keeps the sign-in email in step with the staff email', function () {
    $staff = newStaff(['email' => 'old@greenhill.test']);

    app(UpdateStaff::class)->handle($staff, ['first_name' => 'Kwame', 'last_name' => 'Owusu', 'email' => 'new@greenhill.test']);

    expect($staff->fresh()->user->email)->toBe('new@greenhill.test');
    expect(fn () => app(UpdateStaff::class)->handle($staff->fresh(), ['first_name' => 'Kwame', 'last_name' => 'Owusu', 'email' => '']))
        ->toThrow(ValidationException::class, 'cannot be removed');
});

it('only lets you give roles whose permissions you hold', function () {
    $staff = newStaff(['email' => 'b@greenhill.test']);

    // The administrator has no fees.manage, so cannot make someone bursar.
    expect(fn () => app(SetStaffRoles::class)->handle($staff, ['bursar'], $this->admin))
        ->toThrow(ValidationException::class, 'Bursar');

    app(SetStaffRoles::class)->handle($staff, ['bursar'], $this->proprietor);
    expect($staff->user->fresh()->hasRole('bursar'))->toBeTrue();

    // ...nor take it away again.
    expect(fn () => app(SetStaffRoles::class)->handle($staff->fresh(), [], $this->admin))
        ->toThrow(ValidationException::class, 'Bursar');
});

it('lets administrators give everyday teaching roles', function () {
    $staff = newStaff(['email' => 't@greenhill.test']);

    app(SetStaffRoles::class)->handle($staff, ['class_teacher', 'subject_teacher', 'gate_staff'], $this->admin);

    expect($staff->user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['class_teacher', 'gate_staff', 'subject_teacher']);
});

it('never gives the parent role to staff', function () {
    $staff = newStaff(['email' => 'p@greenhill.test']);

    app(SetStaffRoles::class)->handle($staff, ['parent'], $this->proprietor);
})->throws(ValidationException::class, 'guardians');

it('stops you removing your own leadership role', function () {
    $mine = Staff::factory()->for($this->school)->create(['user_id' => $this->admin->id, 'email' => $this->admin->email]);

    app(SetStaffRoles::class)->handle($mine, ['class_teacher'], $this->admin);
})->throws(ValidationException::class, 'own leadership');

it('resends invitations only until they are accepted', function () {
    $staff = newStaff(['email' => 'k@greenhill.test']);

    app(ResendInvitation::class)->handle($staff);
    Notification::assertSentToTimes($staff->user, SchoolInvitation::class, 2);

    $staff->user->forceFill(['email_verified_at' => now()])->save();
    expect(fn () => app(ResendInvitation::class)->handle($staff->fresh()))->toThrow(ValidationException::class, 'already accepted');
});

it('switches off the login and assignments of staff who leave', function () {
    setUpTeaching();
    $staff = newStaff(['email' => 'leaver@greenhill.test', 'first_name' => 'Ama'], ['class_teacher']);
    $staff->user->forceFill(['password' => 'Secret-Pass-1', 'email_verified_at' => now()])->save();
    app(AssignClassTeacher::class)->handle(jhs2A(), $staff);
    app(AssignTeacher::class)->handle(jhs2A(), Subject::where('name', 'French')->sole(), $staff);

    app(MarkStaffLeft::class)->handle($staff, $this->admin);

    expect($staff->fresh()->status)->toBe(StaffStatus::Left)
        ->and($staff->user->fresh()->deactivated_at)->not->toBeNull()
        ->and(TeachingAssignment::count())->toBe(0)
        ->and(ClassTeacherAssignment::count())->toBe(0);

    $this->onSchool($this->school);
    $this->post('http://greenhill.localhost/login', ['email' => 'leaver@greenhill.test', 'password' => 'Secret-Pass-1'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('signs out a staff member who leaves mid-session', function () {
    $staff = newStaff(['email' => 'leaver@greenhill.test']);
    $this->onSchool($this->school);
    $this->actingAs($staff->user);

    app(MarkStaffLeft::class)->handle($staff, $this->admin);
    auth()->forgetGuards();

    $this->get('http://greenhill.localhost/dashboard')->assertRedirect('http://greenhill.localhost/login');
});

it('cannot mark yourself as having left', function () {
    $mine = Staff::factory()->for($this->school)->create(['user_id' => $this->admin->id]);

    app(MarkStaffLeft::class)->handle($mine, $this->admin);
})->throws(ValidationException::class, 'yourself');

it('assigns one teacher per subject and stream for the current year', function () {
    setUpTeaching();
    $french = Subject::where('name', 'French')->sole();
    $first = newStaff(['first_name' => 'Ama']);
    $second = newStaff(['first_name' => 'Yaw']);

    app(AssignTeacher::class)->handle(jhs2A(), $french, $first);
    app(AssignTeacher::class)->handle(jhs2A(), $french, $second);

    expect(TeachingAssignment::sole()->staff->is($second))->toBeTrue();

    app(AssignTeacher::class)->handle(jhs2A(), $french, null);
    expect(TeachingAssignment::count())->toBe(0);
});

it('only assigns subjects the class takes, and only active staff', function () {
    setUpTeaching();
    $staff = newStaff();
    $foreignSubject = Subject::factory()->for($this->school)->create(['name' => 'Not taken', 'levels' => [SchoolLevel::Jhs]]);

    expect(fn () => app(AssignTeacher::class)->handle(jhs2A(), $foreignSubject, $staff))
        ->toThrow(ValidationException::class, 'does not take');

    $staff->forceFill(['status' => StaffStatus::Left])->save();
    expect(fn () => app(AssignClassTeacher::class)->handle(jhs2A(), $staff))
        ->toThrow(ValidationException::class, 'has left');
});

it('sets and replaces the class teacher', function () {
    setUpTeaching();
    $a = newStaff(['first_name' => 'Ama']);
    $b = newStaff(['first_name' => 'Yaw']);

    app(AssignClassTeacher::class)->handle(jhs2A(), $a);
    app(AssignClassTeacher::class)->handle(jhs2A(), $b);

    expect(ClassTeacherAssignment::sole()->staff->is($b))->toBeTrue();
});

it('needs a current academic year to assign', function () {
    app(GenerateDefaultClasses::class)->handle();

    app(AssignClassTeacher::class)->handle(jhs2A(), newStaff());
})->throws(ValidationException::class, 'current academic year');

describe('screens', function () {
    beforeEach(function () {
        $this->teacher = User::factory()->for($this->school)->create();
        $this->teacher->assignRole(RoleKey::ClassTeacher->value);
        $this->onSchool($this->school);
    });

    it('refuses teachers', function (string $path) {
        $this->actingAs($this->teacher)->get("http://greenhill.localhost{$path}")->assertForbidden();
    })->with(['/staff', '/staff/teaching-assignments']);

    it('lists staff for administrators and links from the sidebar', function () {
        newStaff(['first_name' => 'Efua', 'last_name' => 'Mensah']);

        $this->actingAs($this->admin)->get('http://greenhill.localhost/staff')
            ->assertOk()->assertSee('Efua Mensah')->assertSee('Teaching assignments');
    });

    it('adds staff from the screen, without roles unless you can manage roles', function () {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startCreate')
            ->set('form.first_name', 'Yaw')
            ->set('form.last_name', 'Boateng')
            ->set('form.email', 'yaw@greenhill.test')
            ->set('roles', ['subject_teacher'])
            ->call('save')
            ->assertHasNoErrors();

        expect(Staff::where('first_name', 'Yaw')->sole()->user->hasRole('subject_teacher'))->toBeTrue();
    });

    it('shows Action errors on the form', function () {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startCreate')
            ->set('form.first_name', '')
            ->set('form.last_name', 'Boateng')
            ->call('save')
            ->assertHasErrors('first_name');
    });

    it('assigns teachers from the assignments screen', function () {
        setUpTeaching();
        $staff = newStaff();
        $french = Subject::where('name', 'French')->sole();

        Livewire::actingAs($this->admin)->test(TeachingAssignments::class)
            ->call('selectStream', jhs2A()->id)
            ->call('assignTeacher', $french->id, (string) $staff->id)
            ->call('assignClassTeacher', (string) $staff->id);

        expect(TeachingAssignment::sole()->staff_id)->toBe($staff->id)
            ->and(ClassTeacherAssignment::sole()->staff_id)->toBe($staff->id);
    });
});
