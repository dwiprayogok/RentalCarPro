<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRecord> */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'type' => 'servis_rutin',
            'title' => 'Ganti oli & filter',
            'performed_at' => now()->subDays(3)->toDateString(),
            'odometer' => 10000,
            'cost' => 450000,
            'vendor' => 'Bengkel Resmi',
        ];
    }
}
