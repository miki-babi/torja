<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\MealProgramItem;
use App\Models\MealProgramMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealProgramItem>
 */
class MealProgramItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_program_meal_id' => MealProgramMeal::factory(),
            'food_id' => Food::factory(),
            'portion_note' => null,
            'position' => 0,
        ];
    }
}
