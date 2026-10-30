<?php

namespace Database\Factories;

use App\Models\TelegramBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramBroadcast>
 */
class TelegramBroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => 'text',
            'body_html' => '<p>Hello <strong>there</strong></p>',
            'telegram_text' => 'Hello <b>there</b>',
            'media' => null,
            'buttons' => null,
            'poll' => null,
            'options' => ['disable_notification' => false, 'protect_content' => false],
            'audience' => ['everyone' => true, 'segments' => [], 'user_ids' => []],
            'status' => 'draft',
            'recurrence' => 'none',
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * A broadcast queued for dispatch.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'next_run_at' => now()->subMinute(),
        ]);
    }
}
