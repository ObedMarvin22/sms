<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\PlatformRoleKey;
use App\Models\Activity;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->greenhill = School::factory()->create();
    $this->hillview = School::factory()->create();
    app(SeedDefaultRoles::class)->handle($this->greenhill);
    app(SeedDefaultRoles::class)->handle($this->hillview);

    $this->current = app(CurrentSchool::class);
    $this->staff = User::factory()->for($this->greenhill)->create(['name' => 'Kofi Boateng', 'email' => 'kofi@greenhill.test']);
    $this->head = User::factory()->for($this->greenhill)->create();
});

function latestActivity(string $logName): Activity
{
    return Activity::withoutGlobalScopes()->where('log_name', $logName)->latest('id')->firstOrFail();
}

it('logs name and email changes with old and new values', function () {
    $this->current->runFor($this->greenhill, function () {
        $this->actingAs($this->head);
        $this->staff->update(['name' => 'Kofi B. Boateng', 'email' => 'kofi.b@greenhill.test']);
    });

    $entry = latestActivity('users');

    expect($entry->event)->toBe('updated')
        ->and($entry->school_id)->toBe($this->greenhill->id)
        ->and($entry->subject->is($this->staff))->toBeTrue()
        ->and($entry->causer->is($this->head))->toBeTrue()
        ->and($entry->attribute_changes['old'])->toBe(['name' => 'Kofi Boateng', 'email' => 'kofi@greenhill.test'])
        ->and($entry->attribute_changes['attributes'])->toBe(['name' => 'Kofi B. Boateng', 'email' => 'kofi.b@greenhill.test']);
});

it('logs a password change without the password', function () {
    $this->current->runFor($this->greenhill, fn () => $this->staff->update(['password' => 'A-New-Secret-123']));

    $entry = latestActivity('users');
    $everything = json_encode(Activity::withoutGlobalScopes()->get()->toArray());

    expect($entry->event)->toBe('password_changed')
        ->and($everything)->not->toContain('A-New-Secret-123')
        ->and($everything)->not->toContain($this->staff->fresh()->password);
});

it('does not log changes to fields outside the audit list', function () {
    $before = Activity::withoutGlobalScopes()->count();

    $this->staff->forceFill(['remember_token' => 'abc'])->save();

    expect(Activity::withoutGlobalScopes()->count())->toBe($before);
});

it('logs a role being given and taken away, in the right school', function () {
    $this->current->runFor($this->greenhill, function () {
        $this->actingAs($this->head);

        $this->staff->assignRole('bursar');
        expect(latestActivity('roles'))
            ->event->toBe('role_assigned')
            ->properties->toArray()->toBe(['roles' => ['bursar']])
            ->school_id->toBe($this->greenhill->id);

        $this->staff->removeRole('bursar');
        expect(latestActivity('roles'))
            ->event->toBe('role_removed')
            ->properties->toArray()->toBe(['roles' => ['bursar']]);

        expect(latestActivity('roles')->causer->is($this->head))->toBeTrue();
    });
});

it('logs permissions granted to and revoked from a role', function () {
    $this->current->runFor($this->greenhill, function () {
        $gate = Role::where('school_id', $this->greenhill->id)->where('name', 'gate_staff')->sole();

        $gate->givePermissionTo('students.view');
        expect(latestActivity('roles'))
            ->event->toBe('permission_granted')
            ->properties->toArray()->toBe(['permissions' => ['students.view']])
            ->subject_id->toBe($gate->id);

        $gate->revokePermissionTo('students.view');
        expect(latestActivity('roles'))->event->toBe('permission_revoked');
    });
});

it("hides one school's log from another school", function () {
    $this->current->runFor($this->greenhill, fn () => $this->staff->update(['name' => 'Changed at Greenhill']));

    $this->current->runFor($this->hillview, function () {
        expect(Activity::where('description', 'updated')->count())->toBe(0);
    });

    $this->current->runFor($this->greenhill, function () {
        expect(Activity::where('description', 'updated')->count())->toBe(1);
    });
});

it('records console actions with the platform user and the subject\'s school', function () {
    $superAdmin = User::factory()->platform(PlatformRoleKey::SuperAdmin)->create();
    $this->actingAs($superAdmin, 'platform');

    // No current school: as on the admin subdomain.
    $this->staff->update(['name' => 'Fixed by support']);

    $entry = latestActivity('users');

    expect($entry->causer->is($superAdmin))->toBeTrue()
        ->and($entry->school_id)->toBe($this->greenhill->id);
});

it('records platform-only events with no school', function () {
    $platformUser = User::factory()->platform(PlatformRoleKey::Support)->create();

    // platform_role is not mass-assignable, so set it explicitly.
    $platformUser->platform_role = PlatformRoleKey::SuperAdmin;
    $platformUser->save();

    $entry = latestActivity('users');

    expect($entry->school_id)->toBeNull()
        ->and($entry->attribute_changes['old'])->toBe(['platform_role' => 'support'])
        ->and($entry->attribute_changes['attributes'])->toBe(['platform_role' => 'super_admin']);
});
