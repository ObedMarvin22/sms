<?php

use App\Enums\PermissionKey;
use App\Http\Controllers\Api\V1\ChildAttendanceController;
use App\Http\Controllers\Api\V1\ChildFeesController;
use App\Http\Controllers\Api\V1\ChildPaymentController;
use App\Http\Controllers\Api\V1\ChildrenController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\ParentAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every API route runs inside a school: {school}.APP_BASE_DOMAIN/api/...
Route::domain('{school}.'.config('app.base_domain'))
    ->middleware(['school', 'school.member'])
    ->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');

        // The parent app (SPEC section 5). Limits: AppServiceProvider::configureRateLimiting().
        Route::prefix('v1')->name('api.v1.')->group(function () {
            Route::post('auth/otp', [ParentAuthController::class, 'requestCode'])
                ->middleware('throttle:parent-otp')->name('auth.otp');
            Route::post('auth/set-password', [ParentAuthController::class, 'setPassword'])
                ->middleware('throttle:parent-code')->name('auth.set-password');
            Route::post('auth/login', [ParentAuthController::class, 'login'])
                ->middleware('throttle:parent-login')->name('auth.login');

            // portal.children: the Parent role's permission, so a school can switch the app off.
            Route::middleware(['auth:sanctum', 'parent', 'can:'.PermissionKey::PortalChildren->value])->group(function () {
                Route::post('auth/logout', [ParentAuthController::class, 'logout'])->name('auth.logout');
                Route::get('me', MeController::class)->name('me');

                // {student} is only ever looked up through the guardian's links (FindParentChild).
                Route::get('children', [ChildrenController::class, 'index'])->name('children.index');
                Route::get('children/{student}', [ChildrenController::class, 'show'])->whereNumber('student')->name('children.show');
                Route::get('children/{student}/attendance', ChildAttendanceController::class)->whereNumber('student')->name('children.attendance');
                Route::get('children/{student}/bills', [ChildFeesController::class, 'bills'])->whereNumber('student')->name('children.bills');
                Route::get('children/{student}/statement', [ChildFeesController::class, 'statement'])->whereNumber('student')->name('children.statement');
                Route::post('children/{student}/payments', ChildPaymentController::class)->whereNumber('student')
                    ->middleware('throttle:parent-payment')->name('children.payments');

                Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
                Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
                Route::post('notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
            });
        });
    });
