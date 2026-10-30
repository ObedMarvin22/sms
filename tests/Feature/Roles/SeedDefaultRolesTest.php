<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->seed = app(SeedDefaultRoles::class);
});

/**
 * @return array<string, list<string>> role name => sorted permission names, for one school
 */
function rolesOf(School $school): array
{
    return Role::with('permissions')
        ->where('school_id', $school->id)
        ->get()
        ->mapWithKeys(fn (Role $role) => [
            $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
        ])
        ->sortKeys()
        ->all();
}

it('creates the nine default roles with their default permissions', function () {
    $this->seed->handle($this->schoolA);

    $expected = collect(RoleKey::cases())
        ->mapWithKeys(fn (RoleKey $key) => [
            $key->value => collect($key->defaultPermissions())->map->value->sort()->values()->all(),
        ])
        ->sortKeys()
        ->all();

    expect(rolesOf($this->schoolA))->toBe($expected);
});

it('matches the agreed permission table for the sensitive cases', function () {
    $this->seed->handle($this->schoolA);
    $roles = rolesOf($this->schoolA);

    expect($roles['gate_staff'])->toBe(['pickup.release'])
        ->and($roles['parent'])->toBe(['portal.children'])
        ->and($roles['headteacher'])->not->toContain('fees.manage', 'payments.record')
        ->and($roles['headteacher'])->toContain('finance.view', 'grades.approve')
        ->and($roles['bursar'])->toContain('fees.manage', 'payments.record')
        ->and($roles['proprietor'])->toHaveCount(count(PermissionKey::cases()) - 1);
});

it('creates each permission once, shared by all schools', function () {
    $this->seed->handle($this->schoolA);
    $this->seed->handle($this->schoolB);

    expect(Permission::count())->toBe(count(PermissionKey::cases()))
        ->and(Role::count())->toBe(2 * count(RoleKey::cases()));
});

it('gives each school its own copy of the roles', function () {
    $this->seed->handle($this->schoolA);

    expect(rolesOf($this->schoolA))->toHaveCount(9)
        ->and(rolesOf($this->schoolB))->toBe([]);
});

it('is safe to run again and keeps a school\'s own edits', function () {
    $this->seed->handle($this->schoolA);

    $bursar = Role::where('school_id', $this->schoolA->id)->where('name', 'bursar')->firstOrFail();
    $bursar->revokePermissionTo('payments.record');

    $this->seed->handle($this->schoolA);

    expect(Role::where('school_id', $this->schoolA->id)->count())->toBe(9)
        ->and(rolesOf($this->schoolA)['bursar'])->not->toContain('payments.record');
});

it('restores the previous current school afterwards', function () {
    app(CurrentSchool::class)->set($this->schoolB);

    $this->seed->handle($this->schoolA);

    expect(app(CurrentSchool::class)->id())->toBe($this->schoolB->id);
});

it('applies a role only within the school where it was given', function () {
    $this->seed->handle($this->schoolA);
    $this->seed->handle($this->schoolB);
    $user = User::factory()->for($this->schoolA)->create();
    $current = app(CurrentSchool::class);

    $current->runFor($this->schoolA, fn () => $user->assignRole(RoleKey::Headteacher->value));

    $current->runFor($this->schoolA, function () use ($user) {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        expect($user->hasRole('headteacher'))->toBeTrue()
            ->and($user->can('grades.approve'))->toBeTrue()
            ->and($user->can('fees.manage'))->toBeFalse();
    });

    $current->runFor($this->schoolB, function () use ($user) {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        expect($user->hasRole('headteacher'))->toBeFalse()
            ->and($user->can('grades.approve'))->toBeFalse();
    });
});

it('uses the current school as the permission team', function () {
    expect(getPermissionsTeamId())->toBeNull();

    app(CurrentSchool::class)->runFor($this->schoolA, function () {
        expect(getPermissionsTeamId())->toBe($this->schoolA->id);
    });

    expect(getPermissionsTeamId())->toBeNull();
});
