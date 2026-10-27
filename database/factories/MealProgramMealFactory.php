<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\MealProgram;
use App\Models\MealProgramMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealProgramMeal>
 */
class MealProgramMealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_program_id' => MealProgram::factory(),
            'day' => fake()->numberBetween(1, 7),
            'meal_type' => fake()->randomElement(MealType::cases()),
            'position' => 0,
        ];
    }
}
