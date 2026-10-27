<?php

namespace Database\Factories;

use App\Models\MealProgram;
use App\Models\MealProgramAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealProgramAccess>
 */
class MealProgramAccessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MealProgramAccess>
     */
    protected $model = MealProgramAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'meal_program_id' => MealProgram::factory(),
            'payment_id' => null,
        ];
    }
}
