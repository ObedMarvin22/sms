<?php

use App\Enums\PermissionKey;
use App\Http\Controllers\SchoolLogoController;
use App\Livewire\Academic\CreateYear as CreateAcademicYear;
use App\Livewire\Academic\Years as AcademicYears;
use App\Livewire\Console\Login as ConsoleLogin;
use App\Livewire\Console\Schools\Create as ConsoleSchoolsCreate;
use App\Livewire\Console\Schools\Index as ConsoleSchoolsIndex;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\School\Profile as SchoolProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Orange Tree console: admin.APP_BASE_DOMAIN, "platform" guard, no school.
| Registered first so "admin" is never treated as a school subdomain.
*/
Route::domain('admin.'.config('app.base_domain'))
    ->name('console.')
    ->group(function () {
        Route::livewire('login', ConsoleLogin::class)->middleware('guest:platform')->name('login');

        Route::middleware('auth:platform')->group(function () {
            Route::redirect('/', '/schools')->name('home');
            Route::livewire('schools', ConsoleSchoolsIndex::class)->name('schools.index');
            Route::livewire('schools/create', ConsoleSchoolsCreate::class)->name('schools.create');

            Route::post('logout', function (Request $request) {
                Auth::guard('platform')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('console.login');
            })->name('logout');
        });
    });

/*
| School routes: {school}.APP_BASE_DOMAIN, resolved by the IdentifySchool middleware ("school").
| Registered before the bare-domain routes so a subdomain never falls through to them.
| Fortify's auth routes join this group through config/fortify.php (domain + middleware).
*/
Route::domain('{school}.'.config('app.base_domain'))
    ->middleware(['school', 'school.member'])
    ->group(function () {
        Route::redirect('/', '/login')->name('school.home');

        // This school's logo only (private disk). Public: the login page shows it.
        Route::get('branding/logo', SchoolLogoController::class)->name('school.logo');

        // Signed, single-use link from SchoolInvitation (App\Support\Invitations\InvitationLink).
        Route::livewire('invitation/{user}/{fingerprint}', AcceptInvitation::class)
            ->middleware('signed')
            ->name('invitation.accept');

        // Staff screens. Settings (incl. two-factor setup) are in routes/settings.php, outside
        // this group, so staff who must set up two-factor can still reach them.
        Route::middleware(['auth', 'verified', 'two-factor.required'])->group(function () {
            Route::view('dashboard', 'dashboard')->name('dashboard');

            Route::middleware('can:'.PermissionKey::SettingsManage->value)->group(function () {
                Route::livewire('settings/school', SchoolProfile::class)->name('school.profile');
                Route::livewire('settings/academic-years', AcademicYears::class)->name('academic-years.index');
                Route::livewire('settings/academic-years/create', CreateAcademicYear::class)->name('academic-years.create');
            });
        });

        require __DIR__.'/settings.php';
    });

// Bare domain: public landing page (no school).
Route::domain(config('app.base_domain'))->group(function () {
    Route::view('/', 'welcome')->name('home');
});
