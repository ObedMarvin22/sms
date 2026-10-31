<?php

use App\Enums\PermissionKey;
use App\Http\Controllers\Api\V1\MeController;
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
            });
        });
    });
