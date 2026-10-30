<?php

namespace Database\Factories;

use App\Models\ProGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProGrant>
 */
class ProGrantFactory extends Factory
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
            'source' => 'referral_reward',
            'referral_count' => 5,
            'granted_by_id' => null,
            'revoked_at' => null,
            'revoked_by_id' => null,
            'revoke_reason' => null,
        ];
    }

    /**
     * A grant handed out manually by an admin.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'manual',
            'referral_count' => null,
            'granted_by_id' => User::factory()->admin(),
        ]);
    }

    /**
     * A revoked grant.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'revoked_by_id' => User::factory()->admin(),
            'revoke_reason' => 'Revoked',
        ]);
    }
}
