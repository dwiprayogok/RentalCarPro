<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\BookingRequestController;
use App\Http\Controllers\Api\V1\CarAvailabilityController;
use App\Http\Controllers\Api\V1\CarController;
use App\Http\Controllers\Api\V1\CarPhotoController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDocumentController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\MaintenanceRecordController;
use App\Http\Controllers\Api\V1\RentalController;
use App\Http\Controllers\Api\V1\RentalInspectionController;
use App\Http\Controllers\Api\V1\RentalInvoiceController;
use App\Http\Controllers\Api\V1\RentalPaymentController;
use App\Http\Controllers\Api\V1\RentalReturnController;
use App\Http\Controllers\Api\V1\ReportController;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        // Pengguna & role (user.manage)
        Route::get('roles', [UserController::class, 'roles'])->name('roles.index');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::post('users/{user}/force-password-reset', [UserController::class, 'forceReset'])->name('users.force-reset');

        // Log aktivitas (activity.view)
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

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
        Route::post('rentals/{rental}/return/preview', [RentalReturnController::class, 'preview'])->name('rentals.return.preview');
        Route::post('rentals/{rental}/return', [RentalReturnController::class, 'store'])->name('rentals.return');
        Route::get('rentals/{rental}/invoice', [RentalInvoiceController::class, 'show'])->name('rentals.invoice');
        Route::get('rentals/{rental}/invoice/pdf', [RentalInvoiceController::class, 'pdf'])->name('rentals.invoice.pdf');
        Route::post('rentals/{rental}/invoice/print-url', [RentalInvoiceController::class, 'printUrl'])->name('rentals.invoice.print-url');
        Route::post('rentals/{rental}/payments', [RentalPaymentController::class, 'store'])->name('rentals.payments.store');
        Route::delete('rentals/{rental}/payments/{payment}', [RentalPaymentController::class, 'destroy'])->scopeBindings()->name('rentals.payments.destroy');
        Route::post('rentals/{rental}/inspections/{type}/photos', [RentalInspectionController::class, 'storePhotos'])
            ->whereIn('type', ['serah_terima', 'kembali'])->name('rentals.inspections.photos.store');
        Route::delete('rentals/{rental}/inspections/{type}/photos/{index}', [RentalInspectionController::class, 'destroyPhoto'])
            ->whereIn('type', ['serah_terima', 'kembali'])->whereNumber('index')->name('rentals.inspections.photos.destroy');

        // Permintaan booking (dari landing page)
        Route::get('booking-requests', [BookingRequestController::class, 'index'])->name('booking-requests.index');
        Route::get('booking-requests/{booking_request}', [BookingRequestController::class, 'show'])->name('booking-requests.show');
        Route::patch('booking-requests/{booking_request}', [BookingRequestController::class, 'update'])->name('booking-requests.update');

        // Laporan (report.view)
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('payments', [ReportController::class, 'payments'])->name('payments');
            Route::get('receivables', [ReportController::class, 'receivables'])->name('receivables');
            Route::get('revenue-by-car', [ReportController::class, 'revenueByCar'])->name('revenue-by-car');
            Route::get('utilization', [ReportController::class, 'utilization'])->name('utilization');
            Route::get('maintenance', [ReportController::class, 'maintenance'])->name('maintenance');
            Route::get('profit', [ReportController::class, 'profit'])->name('profit');
            Route::get('{type}/export', [ReportController::class, 'export'])
                ->whereIn('type', ReportController::TYPES)->name('export');
        });

        // Driver & perawatan
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('maintenance', MaintenanceRecordController::class)
            ->parameters(['maintenance' => 'maintenance']);

        // Pengeluaran operasional
        Route::apiResource('expenses', ExpenseController::class);
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
        Route::get('expenses/{expense}/proof', [ExpenseController::class, 'proof'])->name('expenses.proof');
        Route::get('rentals/{rental}/invoice/shared.pdf', [RentalInvoiceController::class, 'shared'])->name('rentals.invoice.shared');
        Route::get('rentals/{rental}/inspections/{type}/photos/{index}', [RentalInspectionController::class, 'photo'])
            ->whereIn('type', ['serah_terima', 'kembali'])->whereNumber('index')->name('rentals.inspections.photo');
    });
});
