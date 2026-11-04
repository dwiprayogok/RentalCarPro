<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+628'.fake()->numerify('##########'),
            'license_number' => fake()->numerify('B1-######'),
            'license_expires_at' => now()->addYear()->toDateString(),
            'daily_fee' => 150000,
            'is_active' => true,
        ];
    }
}
