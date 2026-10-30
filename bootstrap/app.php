<?php

use App\Http\Middleware\EnsureSchoolReadyForStudents;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Http\Middleware\IdentifySchool;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'school' => IdentifySchool::class,
            'school.member' => EnsureUserBelongsToSchool::class,
            'two-factor.required' => EnsureTwoFactorEnabled::class,
            'school.ready' => EnsureSchoolReadyForStudents::class,
        ]);

        // Resolve the school before authentication, and check membership after it
        // (so the user from an API token is known).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: IdentifySchool::class,
        );
        $middleware->appendToPriorityList(
            after: AuthenticatesRequests::class,
            append: EnsureUserBelongsToSchool::class,
        );

        // The console has its own login page; "login" only exists on school subdomains.
        $isConsole = fn (Request $request): bool => str_starts_with($request->getHost(), 'admin.');
        $middleware->redirectGuestsTo(fn (Request $request) => $isConsole($request) ? route('console.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $isConsole($request) ? route('console.home') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
