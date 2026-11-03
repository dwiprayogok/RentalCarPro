<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
| API v1 (Admin Web + aplikasi mobile Flutter).
| Admin Web: Sanctum SPA (cookie). Flutter: token Bearer dari POST /auth/token.
*/
Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')->name('auth.login');
        Route::post('token', [AuthController::class, 'token'])
            ->middleware('throttle:login')->name('auth.token');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:password-reset')->name('auth.forgot');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:password-reset')->name('auth.reset');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        });
    });

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
    });
});
