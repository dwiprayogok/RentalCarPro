<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'category' => 'bbm',
            'title' => 'Isi BBM',
            'spent_at' => now()->subDays(2)->toDateString(),
            'amount' => 300000,
            'method' => 'tunai',
        ];
    }

    /** Pengeluaran umum (tidak terkait mobil). */
    public function general(): static
    {
        return $this->state(['car_id' => null, 'category' => 'gaji', 'title' => 'Gaji staf']);
    }
}
