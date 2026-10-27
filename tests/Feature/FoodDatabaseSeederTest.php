<?php

use App\Enums\FitnessGoal;
use App\Enums\MatchConfidence;
use App\Enums\MealType;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\MealPlanMeal;
use App\Models\NutritionDataset;
use Database\Seeders\FoodDatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(FoodDatabaseSeeder::class));

test('seeds all sections', function () {
    expect(NutritionDataset::count())->toBe(1)
        ->and(NutritionDataset::first()->version)->toBe('1.0.0 (2026-10-24)')
        ->and(Ingredient::count())->toBe(92)
        ->and(Food::count())->toBe(705)
        ->and(DB::table('food_ingredient')->count())->toBe(1502)
        ->and(DB::table('food_swaps')->count())->toBe(1186)
        ->and(MealPlan::count())->toBe(2)
        ->and(MealPlanMeal::count())->toBe(63)
        ->and(MealPlanItem::count())->toBe(169)
        ->and(DB::table('meal_plan_meal_swaps')->count())->toBe(315);
});

test('maps an EPHI food', function () {
    $food = Food::find(1);

    expect($food->efct_code)->toBe('010001')
        ->and($food->name_en)->toBe('Amaranthus, seed, dry, raw')
        ->and($food->name_am)->toBe("Ye'amaranthus fire, ti're")
        ->and($food->kcal_per_100g)->toBe('357.0')
        ->and($food->protein_g)->toBe('14.8')
        ->and($food->is_vegan)->toBeTrue()
        ->and($food->contains_meat)->toBeFalse()
        ->and($food->goal_fat_loss)->toBeFalse()
        ->and($food->meal_types)->toBe([])
        ->and($food->ingredients->pluck('slug')->all())->toBe(['amaranth'])
        ->and($food->review_needed)->toBeFalse();
});

test('maps a plan-only food', function () {
    $food = Food::find(10001);

    expect($food->efct_code)->toBeNull()
        ->and($food->kcal_per_100g)->toBeNull()
        ->and($food->protein_g)->toBeNull()
        ->and($food->carbs_g)->toBeNull()
        ->and($food->fat_g)->toBeNull()
        ->and($food->fibre_g)->toBeNull()
        ->and($food->category)->toBe('Meal plan item (non-dataset)')
        ->and($food->review_needed)->toBeTrue()
        ->and($food->source)->toBe('meal plan PDF');
});

test('keeps corrected data and note', function () {
    $food = Food::find(573);

    expect($food->data_fix_note)->toContain('corrected to milk')
        ->and($food->ingredients->pluck('slug')->all())->toBe(['milk'])
        ->and($food->allergens)->toBe(['milk_dairy'])
        ->and($food->goal_fat_loss)->toBeTrue()
        ->and($food->used_in_plans[0]['day'])->toBeNull();
});

test('food swaps carry reason and order', function () {
    $swaps = Food::find(108)->swaps;

    expect($swaps->pluck('id')->all())->toBe([56, 517, 514, 33, 684])
        ->and($swaps->first()->pivot->reason)->toStartWith('similar kcal, fasting-friendly');
});

test('ingredient maps flags and allergens', function () {
    $wheat = Ingredient::where('slug', 'wheat')->firstOrFail();
    $tofu = Ingredient::where('slug', 'tofu')->firstOrFail();

    expect($wheat->allergens)->toBe(['gluten_cereals'])
        ->and($wheat->common_allergy_label)->toBe('Wheat / gluten')
        ->and($wheat->show_in_onboarding)->toBeTrue()
        ->and($wheat->used_by_foods)->toBe(64)
        ->and($tofu->added_for)->not->toBeNull();
});

test('meal plan structure', function () {
    $fatLoss = MealPlan::where('slug', 'fat_loss_7day')->firstOrFail();
    $muscleGain = MealPlan::where('slug', 'muscle_gain_7day')->firstOrFail();

    expect($fatLoss->goal)->toBe(FitnessGoal::FatLoss)
        ->and($fatLoss->optional_snacks)->toHaveCount(4)
        ->and($fatLoss->meal_totals_stated)->toBeNull();

    $breakfast = $fatLoss->meals->first(
        fn (MealPlanMeal $meal): bool => $meal->day === 1 && $meal->meal_type === MealType::Breakfast
    );

    expect($breakfast->meal_type)->toBe(MealType::Breakfast)
        ->and($breakfast->items->pluck('food_id')->all())->toBe([108, 612])
        ->and($breakfast->items[1]->match_confidence)->toBe(MatchConfidence::Close)
        ->and($breakfast->swaps)->toHaveCount(5)
        ->and($breakfast->swaps->first()->id)->toBe(56)
        ->and($muscleGain->meals)->toHaveCount(42)
        ->and($muscleGain->meal_totals_stated)->not->toBeNull();
});

test('is idempotent', function () {
    $createdAt = Food::find(1)->created_at;
    $ingredientId = Ingredient::where('slug', 'teff')->value('id');

    $this->seed(FoodDatabaseSeeder::class);

    expect(NutritionDataset::count())->toBe(1)
        ->and(Ingredient::count())->toBe(92)
        ->and(Food::count())->toBe(705)
        ->and(DB::table('food_ingredient')->count())->toBe(1502)
        ->and(DB::table('food_swaps')->count())->toBe(1186)
        ->and(MealPlan::count())->toBe(2)
        ->and(MealPlanMeal::count())->toBe(63)
        ->and(MealPlanItem::count())->toBe(169)
        ->and(DB::table('meal_plan_meal_swaps')->count())->toBe(315)
        ->and(Food::find(1)->created_at->equalTo($createdAt))->toBeTrue()
        ->and(Ingredient::where('slug', 'teff')->value('id'))->toBe($ingredientId);
});

test('factories persist', function () {
    expect(Food::factory()->create()->exists)->toBeTrue()
        ->and(Ingredient::factory()->create()->exists)->toBeTrue()
        ->and(MealPlanItem::factory()->create()->exists)->toBeTrue();
});
