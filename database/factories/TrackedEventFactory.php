<?php

namespace Database\Factories;

use App\Models\TrackedEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrackedEvent>
 */
class TrackedEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_uid' => (string) Str::uuid(),
            'visitor_id' => (string) Str::uuid(),
            'user_id' => null,
            'session_id' => null,
            'name' => 'page.view',
            'route_name' => 'home',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => null,
            'context' => null,
            'occurred_at' => now(),
        ];
    }
}
