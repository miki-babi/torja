<?php

namespace Database\Factories;

use App\Models\TrackingIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingIntegration>
 */
class TrackingIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'meta',
            'name' => fake()->words(2, true),
            'external_id' => (string) fake()->randomNumber(8, true),
            'config' => null,
            'scopes' => ['all'],
            'event_map' => ['payment.succeeded' => 'Purchase'],
            'is_enabled' => true,
            'consent_required' => true,
        ];
    }
}
