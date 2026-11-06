<?php

namespace Database\Factories;

use App\Models\CarOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CarOwner> */
class CarOwnerFactory extends Factory
{
    protected $model = CarOwner::class;

    public function definition(): array
    {
        return [
            'name' => 'Pemilik '.fake()->unique()->firstName(),
            'phone' => '+62812'.fake()->unique()->numerify('########'),
            'bank_name' => 'BCA',
            'bank_account_number' => fake()->numerify('##########'),
            'bank_account_name' => 'Pemilik Contoh',
            'share_basis' => 'kotor',
            'share_percent' => 70,
            'is_active' => true,
        ];
    }

    public function net(float $percent = 60): static
    {
        return $this->state(['share_basis' => 'bersih', 'share_percent' => $percent]);
    }
}
