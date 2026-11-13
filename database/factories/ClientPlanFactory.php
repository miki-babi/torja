<?php

namespace Database\Factories;

use App\Models\ClientPlan;
use App\Models\TrainerPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPlan>
 */
class ClientPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_plan_id' => TrainerPlan::factory(),
            'user_id' => User::factory(),
            'payment_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ];
    }

    /**
     * A subscription that has already run out.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'status' => 'expired',
        ]);
    }

    /**
     * A subscription ending inside the renewal-nudge window.
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->addDays(3),
        ]);
    }
}
