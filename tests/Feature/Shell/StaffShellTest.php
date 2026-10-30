<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\School;
use App\Models\User;
use App\Support\Navigation\NavItem;
use App\Support\Navigation\StaffNavigation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->greenhill = School::factory()->active()->create([
        'name' => 'Greenhill Academy', 'subdomain' => 'greenhill', 'primary_colour' => '#0A7D3C',
    ]);
    $this->hillview = School::factory()->active()->create([
        'name' => 'Hillview School', 'subdomain' => 'hillview', 'primary_colour' => '#F5D90A',
    ]);
    app(SeedDefaultRoles::class)->handle($this->greenhill);
    app(SeedDefaultRoles::class)->handle($this->hillview);
});

function member(School $school, RoleKey $role): User
{
    $user = User::factory()->for($school)->withTwoFactor()->create();
    app(CurrentSchool::class)->runFor($school, fn () => $user->assignRole($role->value));

    return $user;
}

it("shows each school's own name and initials", function () {
    $this->actingAs(member($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk()
        ->assertSee('Greenhill Academy')
        ->assertSee('GA')
        ->assertDontSee('Hillview School');

    $this->actingAs(member($this->hillview, RoleKey::ClassTeacher))
        ->get('http://hillview.localhost/dashboard')
        ->assertOk()
        ->assertSee('Hillview School')
        ->assertDontSee('Greenhill Academy');
});

it("applies the school's colour with readable text", function () {
    $this->actingAs(member($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertSee('style="--color-accent: #0a7d3c; --color-accent-content: #0a7d3c; --color-accent-foreground: #ffffff;"', false);

    $this->actingAs(member($this->hillview, RoleKey::ClassTeacher))
        ->get('http://hillview.localhost/dashboard')
        ->assertSee('--color-accent-foreground: #000000;', false);
});

it('ignores an invalid colour instead of writing it into the page', function () {
    // 7 characters, like the column, but not a colour.
    $this->greenhill->update(['primary_colour' => 'red;}a{']);

    $this->actingAs(member($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk()
        ->assertDontSee('--color-accent:', false)
        ->assertDontSee('red;}a{', false);
});

it('drops the starter kit links and shows your roles here', function () {
    $this->actingAs(member($this->greenhill, RoleKey::Administrator))
        ->get('http://greenhill.localhost/dashboard')
        ->assertDontSee('Repository')
        ->assertDontSee('livewire-starter-kit')
        ->assertSee('Administrator / Registrar');
});

it('shows a menu item only to users with its permission in this school', function () {
    // Test-only screens standing in for the Sprint 2 setup pages.
    Route::domain('{school}.'.config('app.base_domain'))->middleware(['web', 'school'])->group(function () {
        Route::get('/_settings-probe', fn () => 'ok')->name('probe.settings');
        Route::get('/_open-probe', fn () => 'ok')->name('probe.open');
    });
    // Names are set after routes are added, so refresh the lookup Route::has() uses.
    app('router')->getRoutes()->refreshNameLookups();
    app()->instance(StaffNavigation::class, new StaffNavigation([
        new NavItem('Dashboard', 'home', 'dashboard'),
        new NavItem('Probe settings', 'cog', 'probe.settings', PermissionKey::SettingsManage, 'School setup'),
        new NavItem('Probe open', 'eye', 'probe.open'),
        new NavItem('Not built yet', 'eye', 'route.that.does.not.exist'),
    ]));

    $this->actingAs(member($this->greenhill, RoleKey::Administrator))
        ->get('http://greenhill.localhost/dashboard')
        ->assertSee('Probe settings')
        ->assertSee('School setup')
        ->assertSee('Probe open')
        ->assertDontSee('Not built yet');

    $this->actingAs(member($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertDontSee('Probe settings')
        ->assertDontSee('School setup')
        ->assertSee('Probe open');
});

it('ignores roles held at another school when building the menu', function () {
    $navigation = new StaffNavigation([
        new NavItem('Dashboard', 'home', 'dashboard'),
        new NavItem('Settings-ish', 'cog', 'dashboard', PermissionKey::SettingsManage, 'School setup'),
    ]);

    // Administrator at Hillview, class teacher at Greenhill.
    $user = member($this->greenhill, RoleKey::ClassTeacher);
    app(CurrentSchool::class)->runFor($this->hillview, fn () => $user->assignRole(RoleKey::Administrator->value));

    app(CurrentSchool::class)->runFor($this->greenhill, function () use ($navigation, $user) {
        expect(array_keys($navigation->forUser($user->fresh())))->toBe(['']);
    });

    app(CurrentSchool::class)->runFor($this->hillview, function () use ($navigation, $user) {
        expect(array_keys($navigation->forUser($user->fresh())))->toBe(['', 'School setup']);
    });
});

it('explains required two-factor on the confirm-password screen', function () {
    $bursar = User::factory()->for($this->greenhill)->create();
    app(CurrentSchool::class)->runFor($this->greenhill, fn () => $bursar->assignRole(RoleKey::Bursar->value));

    $this->actingAs($bursar)
        ->get('http://greenhill.localhost/user/confirm-password')
        ->assertOk()
        ->assertSee('Two-factor authentication required');
});
