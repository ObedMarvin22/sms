<?php

use App\Enums\PermissionKey;
use App\Http\Controllers\ClassListPdfController;
use App\Http\Controllers\ParentAppController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaystackReturnController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SchoolLogoController;
use App\Http\Controllers\StudentImportTemplateController;
use App\Livewire\Academic\CreateYear as CreateAcademicYear;
use App\Livewire\Academic\Years as AcademicYears;
use App\Livewire\Announcements\Index as AnnouncementsIndex;
use App\Livewire\Attendance\Register as AttendanceRegister;
use App\Livewire\Attendance\Today as AttendanceToday;
use App\Livewire\Classes\Index as ClassesIndex;
use App\Livewire\Classes\Subjects as ClassSubjects;
use App\Livewire\Console\Login as ConsoleLogin;
use App\Livewire\Console\Schools\Create as ConsoleSchoolsCreate;
use App\Livewire\Console\Schools\Index as ConsoleSchoolsIndex;
use App\Livewire\Console\Schools\Payments as ConsoleSchoolPayments;
use App\Livewire\Console\Schools\Sms as ConsoleSchoolSms;
use App\Livewire\Fees\Billing as FeeBilling;
use App\Livewire\Fees\Discounts as FeeDiscounts;
use App\Livewire\Fees\InstalmentPlans as FeeInstalmentPlans;
use App\Livewire\Fees\Items as FeeItems;
use App\Livewire\Fees\Messages as FeeMessages;
use App\Livewire\Fees\Payments as FeePayments;
use App\Livewire\Fees\Structures as FeeStructures;
use App\Livewire\Guardians\Index as GuardiansIndex;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\School\Profile as SchoolProfile;
use App\Livewire\Setup\Wizard as SetupWizard;
use App\Livewire\Staff\Index as StaffIndex;
use App\Livewire\Staff\TeachingAssignments as StaffTeachingAssignments;
use App\Livewire\Students\ClassList as StudentsClassList;
use App\Livewire\Students\Import as StudentsImport;
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
            Route::livewire('schools/{school}/payments', ConsoleSchoolPayments::class)->name('schools.payments');
            Route::livewire('schools/{school}/sms', ConsoleSchoolSms::class)->name('schools.sms');

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

        // The parent app (Vue, resources/js/parent). Public shell: sign-in happens in the app,
        // against /api/v1. Screens use hash URLs (/parent#/children/5), so one page serves all.
        Route::get('parent', [ParentAppController::class, 'page'])->name('parent.app');
        Route::get('parent/manifest.webmanifest', [ParentAppController::class, 'manifest'])->name('parent.manifest');
        Route::get('parent/icon-{size}.png', [ParentAppController::class, 'icon'])->whereNumber('size')->name('parent.icon');
        Route::get('parent/sw.js', [ParentAppController::class, 'serviceWorker'])->name('parent.sw');

        // Where Paystack sends a parent back after paying (public: parents may not be signed in).
        Route::get('pay/return', PaystackReturnController::class)->name('paystack.return');

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
                Route::middleware('can:'.PermissionKey::StudentsManage->value)->group(function () {
                    Route::livewire('students/promotion', StudentsPromotion::class)->name('students.promotion');
                    Route::livewire('students/import', StudentsImport::class)->name('students.import');
                    Route::get('students/import/template', StudentImportTemplateController::class)->name('students.import.template');
                });
                Route::livewire('students/{student}', StudentsShow::class)->name('students.show');
                Route::livewire('guardians', GuardiansIndex::class)->name('guardians.index');
                Route::livewire('class-lists', StudentsClassList::class)->name('class-lists.index');
                Route::get('class-lists/{stream}/pdf', ClassListPdfController::class)->name('class-lists.pdf');
            });

            // Registers need a current term and classes. take-attendance: attendance.take or
            // attendance.edit_locked (Gate defined in AppServiceProvider).
            Route::middleware('school.ready')->group(function () {
                Route::livewire('attendance', AttendanceRegister::class)->middleware('can:take-attendance')->name('attendance.register');
                Route::livewire('attendance/today', AttendanceToday::class)
                    ->middleware('can:'.PermissionKey::AttendanceEditLocked->value)
                    ->name('attendance.today');
            });

            // Fees: finance.view to look; changes need fees.manage (checked in each component).
            Route::middleware(['can:'.PermissionKey::FinanceView->value, 'school.ready'])->group(function () {
                Route::livewire('fees/items', FeeItems::class)->name('fees.items');
                Route::livewire('fees/structures', FeeStructures::class)->name('fees.structures');
                Route::livewire('fees/discounts', FeeDiscounts::class)->name('fees.discounts');
                Route::livewire('fees/instalment-plans', FeeInstalmentPlans::class)->name('fees.plans');
                Route::livewire('fees/billing', FeeBilling::class)->name('fees.billing');
                Route::livewire('fees/messages', FeeMessages::class)->name('fees.messages');
            });

            // Announcements to parents. Who may send to whom: AnnouncementRules.
            Route::livewire('announcements', AnnouncementsIndex::class)
                ->middleware(['can:'.PermissionKey::AnnouncementsSend->value, 'school.ready'])
                ->name('announcements.index');

            // Payments and receipts: finance.view or payments.record (Gate in AppServiceProvider).
            Route::middleware(['can:view-payments', 'school.ready'])->group(function () {
                Route::livewire('fees/payments', FeePayments::class)->name('fees.payments');
                Route::get('receipts/{payment}/pdf', ReceiptController::class)->name('receipts.pdf');
                Route::get('payments/{payment}/proof', PaymentProofController::class)->name('payments.proof');
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

    // One webhook for every school; the school is found from our own transaction reference.
    Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');
});
