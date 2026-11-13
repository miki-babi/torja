<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
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
            'week_of' => CheckIn::currentWeek()->toDateString(),
            'weight_kg' => fake()->randomFloat(1, 55, 110),
            'note' => fake()->sentence(),
            'trainer_reply' => null,
            'replied_by' => null,
            'replied_at' => null,
        ];
    }

    /**
     * A check-in the coach has already answered.
     */
    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'trainer_reply' => fake()->sentence(),
            'replied_by' => User::factory()->trainer(),
            'replied_at' => now(),
        ]);
    }

    /**
     * An older check-in, `weeks` weeks back.
     */
    public function weeksAgo(int $weeks): static
    {
        return $this->state(fn (array $attributes) => [
            'week_of' => CheckIn::currentWeek()->subWeeks($weeks)->toDateString(),
        ]);
    }
}
