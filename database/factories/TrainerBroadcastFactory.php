<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerBroadcast>
 */
class TrainerBroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => Trainer::factory(),
            'body' => fake()->sentence(),
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }
}
