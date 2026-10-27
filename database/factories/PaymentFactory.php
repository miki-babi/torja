<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'provider' => 'chapa',
            'tx_ref' => 'tp-'.Str::uuid(),
            'amount' => config('plans.pro_price_etb', 500),
            'currency' => 'ETB',
            'status' => 'pending',
            'purpose' => 'pro',
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'provider_response' => null,
            'paid_at' => null,
            'refunded_at' => null,
        ];
    }

    /**
     * A Verify Checkout attempt with a persisted deposit and idempotency key.
     */
    public function verifyCheckout(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'verify_checkout',
            'deposit_id' => 'dep_'.Str::random(24),
            'idempotency_key' => 'checkout_'.Str::uuid(),
        ]);
    }

    /**
     * A Jami attempt with a persisted checkout session id.
     */
    public function jami(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'jami',
            'deposit_id' => Str::lower(Str::random(24)),
        ]);
    }

    /**
     * A payment confirmed by its provider.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'paid_at' => now(),
        ]);
    }

    /**
     * A payment that failed verification.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * A payment that was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }
}
