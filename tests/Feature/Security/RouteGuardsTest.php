<?php

use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Http\Middleware\IdentifySchool;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Route;
use Livewire\Livewire;

/*
| Fails when a new screen is added without a permission check. Every signed-in school route must
| carry a "can:" check unless it is something every signed-in user may use (listed below).
*/

/**
 * Signed-in school routes that every staff member may open, by route name (or URI if unnamed).
 */
const OPEN_TO_ALL_SIGNED_IN = [
    'dashboard',
    'profile.edit', 'appearance.edit', 'security.edit', 'settings',   // own account settings
    'verification.notice', 'verification.verify', 'verification.send',
    'password.confirm', 'password.confirm.store', 'password.confirmation',
    'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.qr-code',
    'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes',
    'logout',
    'api/user',                                                     // returns the caller only
];

/**
 * @return list<string> resolved middleware (class names with parameters)
 */
function middlewareOf(Route $route): array
{
    return array_values(array_map('strval', app('router')->gatherRouteMiddleware($route)));
}

function routeKey(Route $route): string
{
    return $route->getName() ?? $route->uri();
}

/**
 * @return list<Route>
 */
function routesOnDomain(string $needle): array
{
    return array_values(array_filter(
        app('router')->getRoutes()->getRoutes(),
        fn (Route $route) => str_contains((string) $route->getDomain(), $needle),
    ));
}

it('puts a permission check on every signed-in school screen', function () {
    $unguarded = [];

    foreach (routesOnDomain('{school}') as $route) {
        $middleware = middlewareOf($route);
        $signedIn = collect($middleware)->contains(fn (string $m) => str_starts_with($m, 'Illuminate\Auth\Middleware\Authenticate'));
        $hasPermissionCheck = collect($middleware)->contains(fn (string $m) => str_starts_with($m, 'Illuminate\Auth\Middleware\Authorize:'));

        if ($signedIn && ! $hasPermissionCheck && ! in_array(routeKey($route), OPEN_TO_ALL_SIGNED_IN, true)) {
            $unguarded[] = routeKey($route);
        }
    }

    expect($unguarded)->toBe([], 'Signed-in routes without a can: check: '.implode(', ', $unguarded));
});

it('resolves the school and checks membership on every school route', function () {
    foreach (routesOnDomain('{school}') as $route) {
        // assertContains takes a failure message (Pest's toContain treats extra args as more needles).
        $this->assertContains(IdentifySchool::class, middlewareOf($route), routeKey($route));
        $this->assertContains(EnsureUserBelongsToSchool::class, middlewareOf($route), routeKey($route));
    }
});

it('requires a console login on every console route except the login page', function () {
    $routes = routesOnDomain('admin.');
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        if (routeKey($route) === 'console.login') {
            continue;
        }

        $this->assertContains('Illuminate\Auth\Middleware\Authenticate:platform', middlewareOf($route), routeKey($route));
    }
});

it('keeps re-checking permissions on Livewire updates', function () {
    expect(Livewire::getPersistentMiddleware())
        ->toContain(Authorize::class)
        ->toContain(Authenticate::class);
});
