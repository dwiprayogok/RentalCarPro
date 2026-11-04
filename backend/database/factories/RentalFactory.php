<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory sederhana untuk menyiapkan data uji. Pembuatan sewa sungguhan
 * (nomor nota, cek bentrok, hitung harga) dilakukan lewat service di Phase 3.
 *
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    protected $model = Rental::class;

    public function definition(): array
    {
        $start = now()->addDay()->startOfHour();

        return [
            'code' => 'TRX-TEST-'.fake()->unique()->numerify('#####'),
            'customer_id' => Customer::factory(),
            'car_id' => Car::factory(),
            'service_type' => 'lepas_kunci',
            'status' => RentalStatus::Booking,
            'start_at' => $start,
            'planned_end_at' => $start->copy()->addDay(),
            'daily_rate' => 550000,
            'billed_days' => 1,
            'rental_amount' => 550000,
            'total_amount' => 550000,
        ];
    }

    public function status(RentalStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
