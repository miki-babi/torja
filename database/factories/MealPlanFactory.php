<?php

namespace Database\Factories;

use App\Enums\FitnessGoal;
use App\Models\MealPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlan>
 */
class MealPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'goal' => fake()->randomElement(FitnessGoal::cases()),
            'tags' => [],
            'meal_totals_stated' => null,
            'optional_snacks' => null,
        ];
    }
}
