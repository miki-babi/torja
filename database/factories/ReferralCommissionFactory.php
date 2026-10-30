<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralCommission>
 */
class ReferralCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_user_id' => User::factory(),
            'referred_user_id' => User::factory(),
            'payment_id' => Payment::factory()->successful(),
            'payout_id' => null,
            'amount' => 100,
            'status' => 'pending',
            'approved_by_id' => null,
            'approved_at' => null,
            'paid_at' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    /**
     * A commission approved for payout.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by_id' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    /**
     * A voided commission.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => 'Rejected',
        ]);
    }
}
