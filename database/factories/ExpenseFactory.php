<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'amount' => $this->faker->randomFloat(2, 1, 500),
            'description' => $this->faker->optional()->sentence(),
            'receipt_path' => null,
            'date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'category' => $this->faker->optional()->randomElement(['Food', 'Transportation', 'Shopping', 'Utilities', 'Entertainment', 'Healthcare', 'Other']),
        ];
    }
}
