<?php

namespace Database\Seeders;

use App\Enums\FitnessGoal;
use App\Enums\MatchConfidence;
use App\Enums\MealType;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\MealPlanMeal;
use App\Models\NutritionDataset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports app-food-database.json into the relational food tables.
 *
 * Ingredients, foods, and meal plans are upserted so their ids stay stable across
 * reseeds. Join/child rows (food_ingredient, food_swaps, and each plan's
 * meals/items/swaps) are deleted and reinserted — meal_plan_items.id values are
 * therefore not stable across reseeds; switch to upserts if user data ever
 * references them.
 */
class FoodDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/app-food-database.json');

        if (! is_file($path)) {
            throw new RuntimeException("Food database not found at [{$path}].");
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data): void {
            $this->seedDataset($data);
            $ingredientIds = $this->seedIngredients($data['ingredients']);
            $this->seedFoods($data['foods']);
            $this->seedFoodIngredients($data['foods'], $ingredientIds);
            $this->seedFoodSwaps($data['foods']);
            $this->seedMealPlans($data['meal_plans']);
        });
    }

    /**
     * @param  array{version: string, sources: array<int, string>, goal_tag_rules: array<string, mixed>}  $data
     */
    private function seedDataset(array $data): void
    {
        NutritionDataset::upsert(
            [[
                'version' => $data['version'],
                'sources' => json_encode($data['sources']),
                'goal_tag_rules' => json_encode($data['goal_tag_rules']),
            ]],
            uniqueBy: ['version'],
            update: ['sources', 'goal_tag_rules'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $ingredients
     * @return array<string, int> slug => id map
     */
    private function seedIngredients(array $ingredients): array
    {
        $rows = collect($ingredients)->map(fn (array $row): array => [
            'slug' => $row['id'],
            'name_en' => $row['name_en'],
            'name_am' => $row['name_am'],
            'category' => $row['category'],
            'allergens' => json_encode($row['allergens']),
            'common_allergy_label' => $row['common_allergy_label'],
            'show_in_onboarding' => $row['show_in_onboarding'],
            'is_vegan' => $row['is_vegan'],
            'used_by_foods' => $row['used_by_foods'],
            'added_for' => $row['added_for'] ?? null,
        ]);

        $rows->chunk(100)->each(fn ($chunk) => Ingredient::upsert(
            $chunk->all(),
            uniqueBy: ['slug'],
            update: ['name_en', 'name_am', 'category', 'allergens', 'common_allergy_label', 'show_in_onboarding', 'is_vegan', 'used_by_foods', 'added_for'],
        ));

        return Ingredient::pluck('id', 'slug')->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $foods
     */
    private function seedFoods(array $foods): void
    {
        $rows = collect($foods)->map(fn (array $row): array => $this->mapFood($row));

        $rows->chunk(100)->each(fn ($chunk) => Food::upsert(
            $chunk->all(),
            uniqueBy: ['id'],
            update: ['efct_code', 'name_en', 'name_am', 'category', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g', 'fibre_g', 'allergens', 'meal_types', 'is_fasting_friendly', 'fish_ok_fasting', 'is_vegetarian', 'is_vegan', 'contains_meat', 'goal_fat_loss', 'goal_muscle_gain', 'goal_tag_basis', 'used_in_plans', 'source', 'review_needed', 'data_fix_note'],
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapFood(array $row): array
    {
        preg_match('/\(code (\d+)\)/', $row['source'], $m);

        return [
            'id' => $row['id'],
            'efct_code' => $m[1] ?? null,
            'name_en' => $row['name_en'],
            'name_am' => $row['name_am'],
            'category' => $row['category'],
            'kcal_per_100g' => $row['kcal_per_100g'],
            'protein_g' => $row['protein_g'],
            'carbs_g' => $row['carbs_g'],
            'fat_g' => $row['fat_g'],
            'fibre_g' => $row['fibre_g'],
            'allergens' => json_encode($row['allergens']),
            'meal_types' => json_encode($row['meal_types']),
            'is_fasting_friendly' => $row['diet_flags']['is_fasting_friendly'],
            'fish_ok_fasting' => $row['diet_flags']['fish_ok_fasting'],
            'is_vegetarian' => $row['diet_flags']['vegetarian'],
            'is_vegan' => $row['diet_flags']['vegan'],
            'contains_meat' => $row['diet_flags']['contains_meat'],
            'goal_fat_loss' => $row['goal_tags']['fat_loss'],
            'goal_muscle_gain' => $row['goal_tags']['muscle_gain'],
            'goal_tag_basis' => $row['goal_tags']['basis'],
            'used_in_plans' => json_encode($row['used_in_plans']),
            'source' => $row['source'],
            'review_needed' => $row['review_needed'],
            'data_fix_note' => $row['data_fix_note'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $foods
     * @param  array<string, int>  $ingredientIds
     */
    private function seedFoodIngredients(array $foods, array $ingredientIds): void
    {
        DB::table('food_ingredient')->delete();

        $rows = collect($foods)->flatMap(function (array $food) use ($ingredientIds): array {
            return collect($food['ingredients'])->map(function (string $slug, int $position) use ($food, $ingredientIds): array {
                if (! isset($ingredientIds[$slug])) {
                    throw new RuntimeException("Unknown ingredient [{$slug}] on food {$food['id']} [{$food['name_en']}].");
                }

                return [
                    'food_id' => $food['id'],
                    'ingredient_id' => $ingredientIds[$slug],
                    'position' => $position,
                ];
            })->all();
        });

        $rows->chunk(200)->each(fn ($chunk) => DB::table('food_ingredient')->insert($chunk->all()));
    }

    /**
     * @param  array<int, array<string, mixed>>  $foods
     */
    private function seedFoodSwaps(array $foods): void
    {
        DB::table('food_swaps')->delete();

        $foodIds = collect($foods)->pluck('id')->flip();

        $rows = collect($foods)->flatMap(function (array $food) use ($foodIds): array {
            return collect($food['swaps'])->map(function (array $swap, int $position) use ($food, $foodIds): array {
                if (! isset($foodIds[$swap['food_id']])) {
                    throw new RuntimeException("Unknown swap food {$swap['food_id']} on food {$food['id']} [{$food['name_en']}].");
                }

                return [
                    'food_id' => $food['id'],
                    'swap_food_id' => $swap['food_id'],
                    'reason' => $swap['reason'],
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
        });

        $rows->chunk(100)->each(fn ($chunk) => DB::table('food_swaps')->insert($chunk->all()));
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     */
    private function seedMealPlans(array $plans): void
    {
        $foodIds = Food::pluck('id')->flip();

        foreach ($plans as $plan) {
            $goal = FitnessGoal::from($plan['goal']);

            MealPlan::upsert(
                [[
                    'slug' => $plan['id'],
                    'name' => $plan['plan_name'],
                    'goal' => $goal->value,
                    'tags' => json_encode($plan['tags']),
                    'meal_totals_stated' => isset($plan['meal_totals_stated']) ? json_encode($plan['meal_totals_stated']) : null,
                    'optional_snacks' => isset($plan['optional_snacks']) ? json_encode($plan['optional_snacks']) : null,
                ]],
                uniqueBy: ['slug'],
                update: ['name', 'goal', 'tags', 'meal_totals_stated', 'optional_snacks'],
            );

            $planId = MealPlan::where('slug', $plan['id'])->value('id');

            MealPlanMeal::where('meal_plan_id', $planId)->delete();

            foreach ($plan['days'] as $day) {
                foreach ($day['meals'] as $mealPosition => $meal) {
                    $mealType = MealType::from($meal['meal_type']);

                    $mealId = DB::table('meal_plan_meals')->insertGetId([
                        'meal_plan_id' => $planId,
                        'day' => $day['day'],
                        'meal_type' => $mealType->value,
                        'position' => $mealPosition,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $items = collect($meal['items'])->map(function (array $item, int $position) use ($mealId, $plan, $foodIds): array {
                        MatchConfidence::from($item['match_confidence']);

                        return [
                            'meal_plan_meal_id' => $mealId,
                            'food_id' => $this->requireFoodId($item['food_id'], $plan['id'], $foodIds),
                            'raw_text' => $item['raw_text'],
                            'portion_note' => $item['portion_note'] ?? null,
                            'match_confidence' => $item['match_confidence'],
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    });

                    DB::table('meal_plan_items')->insert($items->all());

                    $swaps = collect($meal['swap_food_ids'] ?? [])->map(fn (array $swap, int $position): array => [
                        'meal_plan_meal_id' => $mealId,
                        'food_id' => $this->requireFoodId($swap['food_id'], $plan['id'], $foodIds),
                        'reason' => $swap['why'],
                        'position' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($swaps->isNotEmpty()) {
                        DB::table('meal_plan_meal_swaps')->insert($swaps->all());
                    }
                }
            }
        }
    }

    /**
     * @param  Collection<int, int>  $foodIds  id => id map
     */
    private function requireFoodId(int $foodId, string $planSlug, Collection $foodIds): int
    {
        if (! isset($foodIds[$foodId])) {
            throw new RuntimeException("Unknown food_id {$foodId} referenced by meal plan [{$planSlug}].");
        }

        return $foodId;
    }
}
