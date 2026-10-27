<?php

namespace Database\Factories;

use App\Models\MealProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MealProgram>
 */
class MealProgramFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price_etb' => 0,
            'share_token' => (string) Str::uuid(),
        ];
    }

    /**
     * A paid program with the given price (in ETB).
     */
    public function paid(int $price = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'price_etb' => $price,
        ]);
    }

    /**
     * Not shared — no public link.
     */
    public function unshared(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_token' => null,
        ]);
    }

    /**
     * Listed in the public /programs catalog.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
        ]);
    }
}
