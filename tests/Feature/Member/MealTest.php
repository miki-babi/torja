<?php

use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FoodDatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.meal'))->assertRedirect(route('login'));
});

test('locked members see only the day-one sample', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal')
            ->where('unlocked', false)
            ->has('targets.kcal_target')
            ->has('targets.protein_g')
            ->has('targets.carbs_g')
            ->has('targets.fat_g')
            ->has('planDays.1.meals')
            ->missing('planDays.2'));
});

test('unlocked members see all plan days', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    Payment::factory()->for($user)->successful()->create();

    $this->actingAs($user)
        ->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal')
            ->where('unlocked', true)
            ->has('planDays.1.meals')
            ->has('planDays.7.meals'));
});

test('targets are null when body data is incomplete', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('targets', null));
});

test('members can view a personalized meal detail page', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $meal = MealPlanMeal::firstOrFail();

    $this->actingAs($user)
        ->get(route('member.meals.show', $meal->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal-show')
            ->where('meal.id', $meal->id)
            ->has('meal.items')
            ->has('planName')
            ->has('day'));
});

test('meal detail applies the profile exclusions', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    $profile = NutritionProfile::factory()->forUser($user)->completed()->create();

    $meal = MealPlanMeal::whereHas('mealPlan', fn ($q) => $q->where('slug', 'fat_loss_7day'))
        ->whereHas('items', fn ($q) => $q->whereNotNull('food_id'))
        ->firstOrFail();
    $item = $meal->items()->whereNotNull('food_id')->firstOrFail();

    $profile->update([
        'avoidances' => ['disliked_food_ids' => [$item->food_id]],
    ]);

    $response = $this->actingAs($user)
        ->get(route('member.meals.show', $meal->id))
        ->assertOk();

    $items = collect($response->viewData('page')['props']['meal']['items']);

    // The disliked food must be swapped out or flagged as excluded.
    expect($items->contains(
        fn ($i) => $i['excluded'] || $i['swapped_from'] !== null,
    ))->toBeTrue();
});

test('locked members cannot open meals beyond day one', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $meal = MealPlanMeal::where('day', 2)->firstOrFail();

    $this->actingAs($user)
        ->get(route('member.meals.show', $meal->id))
        ->assertForbidden();
});

test('unlocked members can open meals beyond day one', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    Payment::factory()->for($user)->successful()->create();

    $meal = MealPlanMeal::where('day', 2)->firstOrFail();

    $this->actingAs($user)
        ->get(route('member.meals.show', $meal->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('day', 2));
});
