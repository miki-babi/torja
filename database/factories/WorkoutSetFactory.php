<?php

namespace Database\Factories;

use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_session_id' => WorkoutSession::factory(),
            'exercise_id' => fake()->slug(),
            'set_index' => 0,
            'weight_kg' => fake()->randomFloat(1, 20, 120),
            'reps' => fake()->numberBetween(5, 15),
        ];
    }
}
