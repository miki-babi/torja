<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'trainer']),
            'slug' => fake()->unique()->slug(2).'-'.fake()->unique()->lexify('??????'),
            'brand_name' => null,
            'bot_token' => null,
            'bot_username' => null,
            'mini_app_name' => null,
            'webhook_secret' => null,
            'webhook_registered_at' => null,
            'menu_button_set_at' => null,
        ];
    }

    /**
     * A trainer with a connected Telegram bot.
     */
    public function withBot(): static
    {
        return $this->state(fn (array $attributes) => [
            'bot_token' => fake()->numerify('##########').':'.fake()->lexify('??????????????????????????????'),
            'bot_username' => fake()->userName().'_bot',
            'webhook_secret' => fake()->lexify('????????????????????????????????????????????????'),
            'webhook_registered_at' => now(),
            'menu_button_set_at' => now(),
        ]);
    }
}
