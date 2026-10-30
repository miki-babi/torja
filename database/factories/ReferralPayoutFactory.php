<?php

namespace Database\Factories;

use App\Models\ReferralPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralPayout>
 */
class ReferralPayoutFactory extends Factory
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
            'amount' => 200,
            'method' => 'telebirr',
            'account' => '0911223344',
            'note' => null,
            'status' => 'requested',
            'processed_by_id' => null,
            'processed_at' => null,
        ];
    }

    /**
     * A settled payout.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'processed_by_id' => User::factory()->admin(),
            'processed_at' => now(),
        ]);
    }

    /**
     * A rejected payout.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'processed_by_id' => User::factory()->admin(),
            'processed_at' => now(),
        ]);
    }
}
