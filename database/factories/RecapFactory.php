<?php

namespace Database\Factories;

use App\Models\Recap;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recap>
 */
class RecapFactory extends Factory
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
            'workout_session_id' => WorkoutSession::factory(),
            'caption' => fake()->optional(0.6)->sentence(),
            'image_path' => null,
        ];
    }
}
