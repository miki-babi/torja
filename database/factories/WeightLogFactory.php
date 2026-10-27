<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WeightLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeightLog>
 */
class WeightLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'logged_on' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(1, 55, 120),
            'source' => 'manual',
        ];
    }
}
