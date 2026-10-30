<?php

use App\Enums\PermissionKey;
use App\Http\Controllers\SchoolLogoController;
use App\Livewire\Academic\CreateYear as CreateAcademicYear;
use App\Livewire\Academic\Years as AcademicYears;
use App\Livewire\Classes\Index as ClassesIndex;
use App\Livewire\Classes\Subjects as ClassSubjects;
use App\Livewire\Console\Login as ConsoleLogin;
use App\Livewire\Console\Schools\Create as ConsoleSchoolsCreate;
use App\Livewire\Console\Schools\Index as ConsoleSchoolsIndex;
use App\Livewire\Guardians\Index as GuardiansIndex;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\School\Profile as SchoolProfile;
use App\Livewire\Setup\Wizard as SetupWizard;
use App\Livewire\Staff\Index as StaffIndex;
use App\Livewire\Staff\TeachingAssignments as StaffTeachingAssignments;
use App\Livewire\Students\Index as StudentsIndex;
use App\Livewire\Students\Promotion as StudentsPromotion;
use App\Livewire\Students\Show as StudentsShow;
use App\Livewire\Subjects\Index as SubjectsIndex;
use App\Livewire\Subjects\Programmes as SubjectsProgrammes;
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

            // Student screens need a current term and a class first (setup wizard).
            Route::middleware(['can:'.PermissionKey::StudentsView->value, 'school.ready'])->group(function () {
                Route::livewire('students', StudentsIndex::class)->name('students.index');
                Route::livewire('students/promotion', StudentsPromotion::class)
                    ->middleware('can:'.PermissionKey::StudentsManage->value)
                    ->name('students.promotion');
                Route::livewire('students/{student}', StudentsShow::class)->name('students.show');
                Route::livewire('guardians', GuardiansIndex::class)->name('guardians.index');
            });

            Route::middleware('can:'.PermissionKey::StaffManage->value)->group(function () {
                Route::livewire('staff', StaffIndex::class)->name('staff.index');
                Route::livewire('staff/teaching-assignments', StaffTeachingAssignments::class)->name('staff.assignments');
            });

            Route::middleware('can:'.PermissionKey::SettingsManage->value)->group(function () {
                Route::livewire('setup', SetupWizard::class)->name('setup');
                Route::livewire('settings/school', SchoolProfile::class)->name('school.profile');
                Route::livewire('settings/academic-years', AcademicYears::class)->name('academic-years.index');
                Route::livewire('settings/academic-years/create', CreateAcademicYear::class)->name('academic-years.create');
                Route::livewire('settings/classes', ClassesIndex::class)->name('classes.index');
                Route::livewire('settings/class-subjects', ClassSubjects::class)->name('class-subjects.index');
                Route::livewire('settings/subjects', SubjectsIndex::class)->name('subjects.index');
                Route::livewire('settings/programmes', SubjectsProgrammes::class)->name('programmes.index');
            });
        });

        require __DIR__.'/settings.php';
    });

// Bare domain: public landing page (no school).
Route::domain(config('app.base_domain'))->group(function () {
    Route::view('/', 'welcome')->name('home');
});
