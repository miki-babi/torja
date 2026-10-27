<?php

namespace Database\Factories;

use App\Models\NutritionDataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionDataset>
 */
class NutritionDatasetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->semver(),
            'sources' => [],
            'goal_tag_rules' => [],
        ];
    }
}
