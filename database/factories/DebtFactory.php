<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Debt>
 */
class DebtFactory extends Factory
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
            'debtor_name' => fake()->name(),
            'amount' => fake()->randomFloat(2, 10, 10000),
            'type' => fake()->randomElement(['owed_to_me', 'i_owe']),
            'description' => fake()->optional()->sentence(),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'is_paid' => fake()->boolean(30),
        ];
    }

    /**
     * Indicate that the debt is owed to me.
     */
    public function owedToMe(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'owed_to_me',
        ]);
    }

    /**
     * Indicate that I owe the debt.
     */
    public function iOwe(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'i_owe',
        ]);
    }

    /**
     * Indicate that the debt is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
        ]);
    }

    /**
     * Indicate that the debt is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
        ]);
    }
}
