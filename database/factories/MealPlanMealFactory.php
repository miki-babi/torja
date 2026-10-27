<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\MealPlan;
use App\Models\MealPlanMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanMeal>
 */
class MealPlanMealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_plan_id' => MealPlan::factory(),
            'day' => fake()->numberBetween(1, 7),
            'meal_type' => fake()->randomElement(MealType::cases()),
            'position' => 0,
        ];
    }
}
