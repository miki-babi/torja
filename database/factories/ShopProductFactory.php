<?php

namespace Database\Factories;

use App\Models\ShopProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopProduct>
 */
class ShopProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'equipment',
            'price_etb' => fake()->numberBetween(200, 5000),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }

    public function equipment(): static
    {
        return $this->state(['category' => 'equipment']);
    }

    public function supplement(): static
    {
        return $this->state(['category' => 'supplement']);
    }
}
