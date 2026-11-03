<?php

namespace Database\Factories;

use App\Models\Recap;
use App\Models\RecapComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecapComment>
 */
class RecapCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recap_id' => Recap::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
