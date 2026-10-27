<?php

namespace Database\Factories;

use App\Models\NutritionProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NutritionProfile>
 */
class NutritionProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_id' => Str::random(40),
            'status' => 'in_progress',
            'current_step' => 1,
            'completed_at' => null,
        ];
    }

    /**
     * Bind the profile to a user.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user?->id ?? User::factory(),
            'session_id' => null,
        ]);
    }

    /**
     * A fully answered, completed profile.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'current_step' => 13,
            'completed_at' => now(),
            'goal' => [
                'primary_goal' => 'lose_fat',
                'motivations' => ['health'],
                'priorities' => ['high_protein'],
            ],
            'body' => [
                'age' => 30,
                'height_cm' => 175,
                'weight_kg' => 80,
                'sex' => 'male',
                'weight_trend' => 'stable',
                'medical_flags' => ['none'],
            ],
            'diet' => [
                'diet_type' => 'omnivore',
                'fasting' => 'none',
                'adapt_to_fasting' => false,
            ],
            'avoidances' => [
                'disliked_food_ids' => [],
                'allergen_codes' => [],
                'excluded_ingredient_ids' => [],
                'discomfort_ingredient_ids' => [],
            ],
        ]);
    }
}
