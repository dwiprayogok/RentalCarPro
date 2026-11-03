<?php

/**
 * Dipanggil oleh Tests\Feature\Rental\ConcurrentBookingTest sebagai PROSES TERPISAH
 * untuk mensimulasikan dua kasir menekan "Simpan" bersamaan.
 *
 * Argumen: car_id customer_id user_id start_iso end_iso
 * Output : "OK <kode nota>" atau "CONFLICT <field>" atau "ERROR <pesan>"
 */

use App\Models\User;
use App\Services\Rental\RentalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $carId, $customerId, $userId, $start, $end] = $argv;

try {
    $rental = app(RentalService::class)->create([
        'car_id' => (int) $carId,
        'customer_id' => (int) $customerId,
        'service_type' => 'lepas_kunci',
        'start_at' => $start,
        'planned_end_at' => $end,
    ], User::findOrFail($userId));

    echo "OK {$rental->code}";
} catch (ValidationException $e) {
    echo 'CONFLICT '.implode(',', array_keys($e->errors()));
} catch (Throwable $e) {
    echo 'ERROR '.get_class($e).': '.$e->getMessage();
}
