<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name_en' => fake()->words(2, true),
            'name_am' => fake()->word(),
            'category' => fake()->randomElement([
                'grain', 'legume', 'nut_seed', 'spice', 'meat', 'fish', 'seafood',
                'egg', 'dairy', 'sweetener', 'oil', 'vegetable', 'root_tuber',
                'fruit', 'beverage', 'other', 'additive',
            ]),
            'allergens' => [],
            'common_allergy_label' => null,
            'show_in_onboarding' => fake()->boolean(),
            'is_vegan' => fake()->boolean(),
            'used_by_foods' => 0,
            'added_for' => null,
        ];
    }
}
