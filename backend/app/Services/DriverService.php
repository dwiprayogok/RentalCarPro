<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Models\Driver;
use Illuminate\Validation\ValidationException;

class DriverService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Driver
    {
        $data['is_active'] ??= true;
        $data['daily_fee'] ??= 0;

        return Driver::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Driver $driver, array $data): Driver
    {
        $data['daily_fee'] ??= 0;
        $driver->update($data);

        return $driver;
    }

    public function delete(Driver $driver): void
    {
        if ($driver->rentals()->whereIn('status', RentalStatus::blocking())->exists()) {
            throw ValidationException::withMessages([
                'driver' => 'Driver masih ditugaskan pada sewa berstatus Booking/Aktif.',
            ]);
        }

        $driver->delete();
    }
}
