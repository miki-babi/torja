<?php

use App\Models\Food;
use App\Models\NutritionProfile;
use App\Models\User;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use Database\Seeders\FoodDatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('plan.preview'))->assertRedirect(route('login'));
});

test('users can view their plan preview with targets and a sample day', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('plan.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan/preview')
            ->where('targets.bmr', 1749)
            ->where('targets.kcal_target', 1599)
            ->where('targets.protein_g', 144)
            ->has('warnings')
            ->where('sampleDay.plan_name', '7-Day Ethiopian-Style Fat Loss Meal Plan')
            ->where('sampleDay.day', 1)
            ->has('sampleDay.meals', 3));
});

test('targets are null when body data is incomplete', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('plan.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('targets', null));
});

test('allergen exclusions are replaced via swaps and annotated', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $profile = NutritionProfile::factory()->completed()->create([
        'avoidances' => [
            'disliked_food_ids' => [],
            'allergen_codes' => ['milk_dairy'],
            'excluded_ingredient_ids' => [],
            'discomfort_ingredient_ids' => [],
        ],
    ]);

    $preview = app(MealPlanPreviewBuilder::class)->for($profile);

    expect($preview['excluded_count'])->toBeGreaterThan(0);

    $foodsByName = Food::all()->keyBy('name_en');

    $swapped = 0;
    foreach ($preview['meals'] as $meal) {
        foreach ($meal['items'] as $item) {
            $food = $foodsByName->get($item['name']);
            expect($food)->not->toBeNull();
            expect(collect($food->allergens)->contains('milk_dairy'))->toBeFalse();
            expect($item['excluded'])->toBeFalse();

            if ($item['swapped_from'] !== null) {
                $swapped++;
                $original = $foodsByName->get($item['swapped_from']['name']);
                expect(collect($original->allergens)->contains('milk_dairy'))
                    ->toBeTrue();
                expect($item['swapped_from']['reason'])->not->toBeNull();
            }
        }
    }

    expect($swapped)->toBe($preview['excluded_count']);
});

test('vegan users only see vegan items', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $profile = NutritionProfile::factory()->completed()->create([
        'diet' => [
            'diet_type' => 'vegan',
            'fasting' => 'none',
            'adapt_to_fasting' => false,
        ],
    ]);

    $preview = app(MealPlanPreviewBuilder::class)->for($profile);

    expect($preview['excluded_count'])->toBeGreaterThan(0);

    $foodsByName = Food::all()->keyBy('name_en');

    foreach ($preview['meals'] as $meal) {
        foreach ($meal['items'] as $item) {
            $food = $foodsByName->get($item['name']);
            expect($food)->not->toBeNull();

            if (! $item['excluded']) {
                expect($food->is_vegan)->toBeTrue();
            }
        }
    }
});

test('disliked foods are excluded from the sample day', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $chicken = Food::find(453);

    $profile = NutritionProfile::factory()->completed()->create([
        'avoidances' => [
            'disliked_food_ids' => [$chicken->id],
            'allergen_codes' => [],
            'excluded_ingredient_ids' => [],
            'discomfort_ingredient_ids' => [],
        ],
    ]);

    $preview = app(MealPlanPreviewBuilder::class)->for($profile);

    $names = collect($preview['meals'])
        ->flatMap(fn (array $meal): array => $meal['items'])
        ->pluck('name');

    expect($names)->not->toContain($chicken->name_en);
});
