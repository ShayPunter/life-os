<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assetTypes = [
            'Backpack',
            'Laptop',
            'Gaming Console',
            'Video Game',
            'Headphones',
            'Smartphone',
            'Camera',
            'Bicycle',
            'Gym Membership',
            'Software Subscription',
        ];

        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->randomElement($assetTypes),
            'description' => $this->faker->optional()->sentence(),
            'cost' => $this->faker->randomFloat(2, 10, 1000),
            'original_cost' => null,
            'original_currency' => null,
            'exchange_rate' => null,
            'uses' => $this->faker->numberBetween(0, 100),
            'hours' => $this->faker->randomFloat(2, 0, 200),
            'tracking_type' => $this->faker->randomElement(['uses', 'hours']),
            'purchased_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
