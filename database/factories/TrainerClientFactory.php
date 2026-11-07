<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerClient>
 */
class TrainerClientFactory extends Factory
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
            'user_id' => User::factory(),
        ];
    }
}
