<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Routing\Route;

/*
| Builds its list from the route table, so every future settings screen is covered automatically.
*/

beforeEach(function () {
    // Offers SHS so the programmes screen is reachable for the allowed roles.
    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary, SchoolLevel::Shs)->create(['subdomain' => 'greenhill']);
    app(SeedDefaultRoles::class)->handle($this->school);
});

/**
 * GET paths of every school route guarded by can:settings.manage.
 *
 * @return list<string>
 */
function settingsPaths(): array
{
    $paths = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $middleware = array_map('strval', app('router')->gatherRouteMiddleware($route));

        if (str_contains((string) $route->getDomain(), '{school}')
            && in_array('GET', $route->methods(), true)
            && in_array('Illuminate\Auth\Middleware\Authorize:settings.manage', $middleware, true)) {
            $paths[] = '/'.$route->uri();
        }
    }

    return $paths;
}

function memberWithRole(School $school, RoleKey $role): User
{
    $user = User::factory()->for($school)->withTwoFactor()->create();
    app(CurrentSchool::class)->runFor($school, fn () => $user->assignRole($role->value));

    return $user;
}

it('finds the settings screens to check', function () {
    expect(settingsPaths())->toContain('/settings/school', '/settings/academic-years', '/settings/classes', '/settings/subjects', '/setup');
});

it('refuses every settings screen to roles without settings.manage', function (RoleKey $role) {
    $user = memberWithRole($this->school, $role);

    foreach (settingsPaths() as $path) {
        $this->actingAs($user)
            ->get("http://greenhill.localhost{$path}")
            ->assertForbidden();
    }
})->with([
    RoleKey::ClassTeacher, RoleKey::SubjectTeacher, RoleKey::Bursar,
    RoleKey::HouseMaster, RoleKey::GateStaff, RoleKey::Parent,
]);

it('refuses every settings screen to a member with no role at all', function () {
    $user = User::factory()->for($this->school)->create();

    foreach (settingsPaths() as $path) {
        $this->actingAs($user)->get("http://greenhill.localhost{$path}")->assertForbidden();
    }
});

it('opens every settings screen to school leaders', function (RoleKey $role) {
    $user = memberWithRole($this->school, $role);

    foreach (settingsPaths() as $path) {
        $this->actingAs($user)->get("http://greenhill.localhost{$path}")->assertOk();
    }
})->with([RoleKey::Proprietor, RoleKey::Headteacher, RoleKey::Administrator]);

it('does not let a leader of another school in', function () {
    $other = School::factory()->active()->create(['subdomain' => 'hillview']);
    app(SeedDefaultRoles::class)->handle($other);
    $foreignAdmin = memberWithRole($other, RoleKey::Administrator);

    foreach (settingsPaths() as $path) {
        $this->actingAs($foreignAdmin)
            ->get("http://greenhill.localhost{$path}")
            ->assertRedirect('http://greenhill.localhost/login');
    }
});

it('sends guests to the login page', function () {
    foreach (settingsPaths() as $path) {
        $this->get("http://greenhill.localhost{$path}")->assertRedirect('http://greenhill.localhost/login');
    }
});

it('keeps the console to Orange Tree staff', function () {
    $schoolAdmin = memberWithRole($this->school, RoleKey::Administrator);
    $support = User::factory()->platform(PlatformRoleKey::Support)->create();

    $this->actingAs($schoolAdmin, 'web')->get('http://admin.localhost/schools')->assertRedirect('http://admin.localhost/login');
    $this->get('http://admin.localhost/schools/create')->assertRedirect('http://admin.localhost/login');

    $this->actingAs($support, 'platform')->get('http://admin.localhost/schools')->assertOk();
});
