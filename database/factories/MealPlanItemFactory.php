<?php

namespace Database\Factories;

use App\Enums\MatchConfidence;
use App\Models\Food;
use App\Models\MealPlanItem;
use App\Models\MealPlanMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanItem>
 */
class MealPlanItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_plan_meal_id' => MealPlanMeal::factory(),
            'food_id' => Food::factory(),
            'raw_text' => fake()->words(3, true),
            'portion_note' => null,
            'match_confidence' => fake()->randomElement(MatchConfidence::cases()),
            'position' => 0,
        ];
    }
}
