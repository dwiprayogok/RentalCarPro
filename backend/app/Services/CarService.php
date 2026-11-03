<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $by): Car
    {
        $data['created_by'] = $by->id;
        $data['seats'] ??= 5;
        $data['default_deposit'] ??= 0;
        $data['is_published'] ??= false;

        return Car::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Car $car, array $data): Car
    {
        if (array_key_exists('slug', $data) && blank($data['slug'])) {
            unset($data['slug']); // pertahankan slug lama bila dikosongkan
        }
        $data['seats'] ??= $car->seats;
        $data['default_deposit'] ??= 0;

        $car->update($data);

        return $car;
    }

    public function delete(Car $car): void
    {
        $hasOpenRentals = $car->rentals()->whereIn('status', RentalStatus::blocking())->exists();
        if ($hasOpenRentals) {
            throw ValidationException::withMessages([
                'car' => 'Mobil masih memiliki sewa berstatus Booking/Aktif. Selesaikan atau batalkan dulu.',
            ]);
        }

        DB::transaction(function () use ($car) {
            $car->forceFill(['is_published' => false])->save();
            $car->delete();
        });
    }
}
