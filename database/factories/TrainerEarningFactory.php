<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerEarning>
 */
class TrainerEarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = 2000.00;

        return [
            'trainer_id' => Trainer::factory(),
            'client_plan_id' => null,
            'payment_id' => Payment::factory(),
            'gross_amount' => $gross,
            'commission_amount' => round($gross * 0.05, 2),
            'net_amount' => round($gross * 0.95, 2),
            'payout_id' => null,
        ];
    }
}
