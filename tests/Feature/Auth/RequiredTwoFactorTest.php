<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\RoleKey;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->active()->create(['subdomain' => 'greenhill']);
    $this->other = School::factory()->active()->create(['subdomain' => 'hillview']);
    app(SeedDefaultRoles::class)->handle($this->school);
    app(SeedDefaultRoles::class)->handle($this->other);
});

function staffWithRole(School $school, RoleKey $role, bool $twoFactor = false): User
{
    $factory = User::factory()->for($school);
    $user = ($twoFactor ? $factory->withTwoFactor() : $factory)->create();

    app(CurrentSchool::class)->runFor($school, fn () => $user->assignRole($role->value));

    return $user;
}

it('sends money and approval roles without two-factor to security settings', function (RoleKey $role) {
    $user = staffWithRole($this->school, $role);

    $this->actingAs($user)
        ->get('http://greenhill.localhost/dashboard')
        ->assertRedirect('http://greenhill.localhost/settings/security');
})->with([RoleKey::Proprietor, RoleKey::Headteacher, RoleKey::Administrator, RoleKey::Bursar]);

it('explains the requirement on the security page and keeps it reachable', function () {
    $user = staffWithRole($this->school, RoleKey::Bursar);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('http://greenhill.localhost/settings/security')
        ->assertOk()
        ->assertSee('Two-factor authentication required')
        ->assertSee('Enable 2FA');
});

it('still lets them log out', function () {
    $user = staffWithRole($this->school, RoleKey::Bursar);

    $this->actingAs($user)->post('http://greenhill.localhost/logout');

    $this->assertGuest();
});

it('opens the dashboard once two-factor is confirmed', function () {
    $user = staffWithRole($this->school, RoleKey::Bursar, twoFactor: true);

    $this->actingAs($user)
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('http://greenhill.localhost/settings/security')
        ->assertDontSee('Two-factor authentication required');
});

it('does not require two-factor for other roles', function (RoleKey $role) {
    $user = staffWithRole($this->school, $role);

    $this->actingAs($user)
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk();
})->with([RoleKey::ClassTeacher, RoleKey::SubjectTeacher, RoleKey::HouseMaster, RoleKey::GateStaff]);

it('only counts roles held in the current school', function () {
    // Bursar at Hillview, class teacher at Greenhill: Greenhill does not demand two-factor.
    $user = staffWithRole($this->school, RoleKey::ClassTeacher);
    app(CurrentSchool::class)->runFor($this->other, fn () => $user->assignRole(RoleKey::Bursar->value));

    app(CurrentSchool::class)->runFor($this->school, fn () => expect($user->fresh()->requiresTwoFactor())->toBeFalse());
    app(CurrentSchool::class)->runFor($this->other, fn () => expect($user->fresh()->requiresTwoFactor())->toBeTrue());

    $this->actingAs($user)
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk();
});

it('re-checks on Livewire update requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureTwoFactorEnabled::class);
});
