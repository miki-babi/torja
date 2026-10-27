<?php

namespace Database\Factories;

use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProgramWorkoutItem>
 */
class ExerciseProgramWorkoutItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_program_workout_id' => ExerciseProgramWorkout::factory(),
            'exercise_id' => fake()->slug(),
            'position' => 0,
        ];
    }
}
