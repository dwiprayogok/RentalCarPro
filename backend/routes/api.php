<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CarAvailabilityController;
use App\Http\Controllers\Api\V1\CarController;
use App\Http\Controllers\Api\V1\CarPhotoController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDocumentController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\MaintenanceRecordController;
use App\Http\Controllers\Api\V1\RentalController;
use App\Http\Controllers\Api\V1\RentalInspectionController;
use App\Http\Controllers\Api\V1\RentalPaymentController;
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

        // Armada
        Route::apiResource('cars', CarController::class);
        Route::get('cars/{car}/availability', CarAvailabilityController::class)->name('cars.availability');
        Route::post('cars/{car}/photos', [CarPhotoController::class, 'store'])->name('cars.photos.store');
        Route::patch('cars/{car}/photos/{photo}', [CarPhotoController::class, 'update'])->scopeBindings()->name('cars.photos.update');
        Route::delete('cars/{car}/photos/{photo}', [CarPhotoController::class, 'destroy'])->scopeBindings()->name('cars.photos.destroy');

        // Pelanggan
        Route::apiResource('customers', CustomerController::class);
        Route::get('customers/{customer}/rentals', [CustomerController::class, 'rentals'])->name('customers.rentals');
        Route::post('customers/{customer}/documents', [CustomerDocumentController::class, 'store'])->name('customers.documents.store');
        Route::delete('customers/{customer}/documents/{type}', [CustomerDocumentController::class, 'destroy'])
            ->whereIn('type', ['ktp', 'sim'])->name('customers.documents.destroy');

        // Transaksi sewa
        Route::get('rentals/export', [RentalController::class, 'export'])->name('rentals.export');
        Route::post('rentals/quote', [RentalController::class, 'quote'])->name('rentals.quote');
        Route::get('rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::post('rentals', [RentalController::class, 'store'])->name('rentals.store');
        Route::get('rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');
        Route::post('rentals/{rental}/handover', [RentalController::class, 'handover'])->name('rentals.handover');
        Route::post('rentals/{rental}/cancel', [RentalController::class, 'cancel'])->name('rentals.cancel');
        Route::post('rentals/{rental}/payments', [RentalPaymentController::class, 'store'])->name('rentals.payments.store');
        Route::delete('rentals/{rental}/payments/{payment}', [RentalPaymentController::class, 'destroy'])->scopeBindings()->name('rentals.payments.destroy');
        Route::post('rentals/{rental}/inspections/{type}/photos', [RentalInspectionController::class, 'storePhotos'])
            ->whereIn('type', ['serah_terima', 'kembali'])->name('rentals.inspections.photos.store');
        Route::delete('rentals/{rental}/inspections/{type}/photos/{index}', [RentalInspectionController::class, 'destroyPhoto'])
            ->whereIn('type', ['serah_terima', 'kembali'])->whereNumber('index')->name('rentals.inspections.photos.destroy');

        // Driver & perawatan
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('maintenance', MaintenanceRecordController::class)
            ->parameters(['maintenance' => 'maintenance']);
    });

    // Berkas KTP/SIM: tanpa sesi (dipakai di <img>), tetapi WAJIB signed URL berumur pendek.
    Route::get('customers/{customer}/documents/{type}/file', [CustomerDocumentController::class, 'file'])
        ->whereIn('type', ['ktp', 'sim'])
        ->middleware(['signed', 'throttle:60,1'])
        ->name('customers.documents.file');

    // Berkas privat transaksi (bukti bayar, foto inspeksi): hanya lewat signed URL.
    Route::middleware(['signed', 'throttle:120,1'])->group(function () {
        Route::get('rentals/{rental}/payments/{payment}/proof', [RentalPaymentController::class, 'proof'])
            ->scopeBindings()->name('rentals.payments.proof');
        Route::get('rentals/{rental}/inspections/{type}/photos/{index}', [RentalInspectionController::class, 'photo'])
            ->whereIn('type', ['serah_terima', 'kembali'])->whereNumber('index')->name('rentals.inspections.photo');
    });
});
