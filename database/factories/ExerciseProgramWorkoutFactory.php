<?php

namespace Database\Factories;

use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProgramWorkout>
 */
class ExerciseProgramWorkoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_program_id' => ExerciseProgram::factory(),
            'title' => fake()->words(2, true),
            'position' => 0,
        ];
    }
}
