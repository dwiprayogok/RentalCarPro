<?php

namespace Database\Factories;

use App\Enums\RiskStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+628'.fake()->numerify('##########'),
            'nik' => '3201'.fake()->unique()->numerify('############'),
            'risk_status' => RiskStatus::Lancar,
        ];
    }

    public function selfDriveReady(): static
    {
        return $this->state(fn () => [
            'sim_number' => fake()->numerify('############'),
            'sim_type' => 'A',
            'sim_expires_at' => now()->addYears(2)->toDateString(),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '+628'.fake()->numerify('##########'),
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(fn () => ['risk_status' => RiskStatus::Blacklist, 'risk_note' => 'Data uji.']);
    }
}
