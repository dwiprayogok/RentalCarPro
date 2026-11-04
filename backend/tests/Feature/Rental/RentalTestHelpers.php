<?php

namespace Tests\Feature\Rental;

use App\Models\Car;
use App\Models\Customer;
use Illuminate\Support\Carbon;

trait RentalTestHelpers
{
    /** Rabu, 21 Okt 2026 15.00 WIB (08.00 UTC). */
    protected function freezeNow(): Carbon
    {
        $now = Carbon::parse('2026-10-21 08:00:00', 'UTC');
        Carbon::setTestNow($now);

        return $now;
    }

    protected function car(array $attrs = []): Car
    {
        return Car::factory()->create(array_merge([
            'rate_self_drive' => 550000,
            'rate_with_driver' => 750000,
            'default_deposit' => 1000000,
            'odometer' => 10000,
        ], $attrs));
    }

    protected function customer(array $attrs = []): Customer
    {
        return Customer::factory()->selfDriveReady()->create($attrs);
    }

    /** Waktu WIB "2026-10-22 10:00" → ISO dengan offset +07:00. */
    protected function wib(string $local): string
    {
        return Carbon::parse($local, 'Asia/Jakarta')->toIso8601String();
    }

    protected function payload(Car $car, Customer $customer, string $start, string $end, array $extra = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'service_type' => 'lepas_kunci',
            'start_at' => $this->wib($start),
            'planned_end_at' => $this->wib($end),
        ], $extra);
    }
}
