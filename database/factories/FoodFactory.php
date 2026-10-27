<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Food>
 */
class FoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'efct_code' => fake()->boolean(80) ? fake()->unique()->numerify('######') : null,
            'name_en' => fake()->words(3, true),
            'name_am' => fake()->word(),
            'category' => fake()->randomElement([
                'Cereals and their products',
                'Starchy roots, tubers, and their products',
                'Legumes and their products',
                'Vegetables and their products',
                'Fruits and their products',
                'Nuts, seeds, and their products',
                'Meat, poultry, and their products',
                'Eggs and their products',
                'Fish, seafood, and their products',
                'Milk and its products',
                'Fats and oils',
                'Beverages',
                'Sugar and sweetened products',
                'Condiments and spices',
                'Miscellaneous',
                'Soups and sauces',
                'Mixed',
                'Meal plan item (non-dataset)',
            ]),
            'kcal_per_100g' => fake()->randomFloat(1, 0, 900),
            'protein_g' => fake()->randomFloat(1, 0, 100),
            'carbs_g' => fake()->randomFloat(1, 0, 100),
            'fat_g' => fake()->randomFloat(1, 0, 100),
            'fibre_g' => fake()->randomFloat(1, 0, 100),
            'allergens' => [],
            'meal_types' => [],
            'is_fasting_friendly' => fake()->boolean(),
            'fish_ok_fasting' => fake()->boolean(),
            'is_vegetarian' => fake()->boolean(),
            'is_vegan' => fake()->boolean(),
            'contains_meat' => fake()->boolean(),
            'goal_fat_loss' => fake()->boolean(),
            'goal_muscle_gain' => fake()->boolean(),
            'goal_tag_basis' => fake()->sentence(),
            'used_in_plans' => [],
            'source' => 'EPHI & FAO, Ethiopian Food Composition Table 2025, condensed table; per 100 g edible portion; carbs = available CHO; fibre = total dietary fibre',
            'review_needed' => false,
            'data_fix_note' => null,
        ];
    }
}
