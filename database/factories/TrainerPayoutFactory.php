<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerPayout>
 */
class TrainerPayoutFactory extends Factory
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
            'amount' => 1900.00,
            'status' => 'pending',
            'note' => null,
            'processed_by' => null,
            'sent_at' => null,
        ];
    }

    /**
     * A payout the staff have already sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'processed_by' => User::factory()->staff(),
            'sent_at' => now(),
        ]);
    }
}
