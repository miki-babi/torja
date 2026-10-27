<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTrait>
 */
class UserTraitFactory extends Factory
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
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
            'primary_goal' => 'lose_fat',
            'plan_slug' => 'fat_loss_7day',
            'computed_at' => now(),
        ];
    }
}
