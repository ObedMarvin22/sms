<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every API route runs inside a school: {school}.APP_BASE_DOMAIN/api/...
Route::domain('{school}.'.config('app.base_domain'))
    ->middleware(['school', 'school.member'])
    ->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');
    });
