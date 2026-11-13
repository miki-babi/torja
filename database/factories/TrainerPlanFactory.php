<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerPlan>
 */
class TrainerPlanFactory extends Factory
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
            'name' => 'Monthly 1:1 Coaching',
            'description' => 'Weekly check-ins, custom program and chat access.',
            'kind' => 'one_on_one',
            'price_etb' => 2000,
            'duration_days' => 30,
            'is_active' => true,
        ];
    }

    /**
     * A plan not shown on the public page.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
