<?php

use Illuminate\Support\Facades\Route;

/*
| School routes: {school}.APP_BASE_DOMAIN, resolved by the IdentifySchool middleware ("school").
| Registered before the bare-domain routes so a subdomain never falls through to them.
| Fortify's auth routes join this group through config/fortify.php (domain + middleware).
*/
Route::domain('{school}.'.config('app.base_domain'))
    ->middleware('school')
    ->group(function () {
        Route::redirect('/', '/login')->name('school.home');

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::view('dashboard', 'dashboard')->name('dashboard');
        });

        require __DIR__.'/settings.php';
    });

// Bare domain: public landing page (no school).
Route::domain(config('app.base_domain'))->group(function () {
    Route::view('/', 'welcome')->name('home');
});
