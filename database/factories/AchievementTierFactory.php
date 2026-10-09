<?php

namespace Database\Factories;

use App\Models\AchievementTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AchievementTier>
 */
class AchievementTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'minimum_points' => fake()->unique()->numberBetween(1, 100000),
        ];
    }
}
