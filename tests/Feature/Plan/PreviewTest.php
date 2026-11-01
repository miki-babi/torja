<?php

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

test('the sample day lists the stored item names without food details', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $profile = NutritionProfile::factory()->completed()->create();

    $preview = app(MealPlanPreviewBuilder::class)->for($profile);

    expect($preview)->not->toHaveKey('excluded_count');

    foreach ($preview['meals'] as $meal) {
        foreach ($meal['items'] as $item) {
            expect($item['name'])->not->toBeEmpty()
                ->and($item)->toHaveKeys(['item_id', 'name', 'portion_note'])
                ->and($item)->not->toHaveKeys(['food_id', 'swap_options', 'macros', 'excluded']);
        }
    }
});
