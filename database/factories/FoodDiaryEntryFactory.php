<?php

namespace Database\Factories;

use App\Models\FoodDiaryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodDiaryEntry>
 */
class FoodDiaryEntryFactory extends Factory
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
            'name' => fake()->words(2, true),
            'detail' => null,
            'kcal' => fake()->randomFloat(1, 100, 800),
            'protein_g' => fake()->randomFloat(1, 5, 50),
            'carbs_g' => fake()->randomFloat(1, 10, 80),
            'fat_g' => fake()->randomFloat(1, 2, 40),
            'food_id' => null,
            'source' => 'search',
            'had_photos' => false,
        ];
    }
}
