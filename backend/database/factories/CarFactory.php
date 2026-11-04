<?php

namespace Database\Factories;

use App\Enums\CarStatus;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Car> */
class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        $rate = fake()->randomElement([300000, 350000, 450000, 550000, 700000]);

        return [
            'brand' => fake()->randomElement(['Toyota', 'Daihatsu', 'Honda', 'Mitsubishi', 'Suzuki']),
            'model' => fake()->randomElement(['Avanza', 'Xenia', 'Brio', 'Xpander', 'Ertiga']),
            'plate_number' => fake()->unique()->bothify('F #### ???'),
            'transmission' => fake()->randomElement(['matic', 'manual']),
            'year' => fake()->numberBetween(2016, 2025),
            'color' => fake()->randomElement(['Hitam', 'Putih', 'Silver']),
            'seats' => 7,
            'rate_self_drive' => $rate,
            'rate_with_driver' => $rate + 200000,
            'default_deposit' => 500000,
            'odometer' => fake()->numberBetween(0, 90000),
            'status' => CarStatus::Tersedia,
            'is_published' => false,
        ];
    }

    public function status(CarStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
