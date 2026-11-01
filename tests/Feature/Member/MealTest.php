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

test('members without a meal plan get an empty plan payload', function () {
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
            ->where('planDays', []));
});

test('unlocked members see all days of their own plan', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    Payment::factory()->for($user)->successful()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Two days',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['name' => 'Oats']]]]],
            ['meals' => [['meal_type' => 'dinner', 'items' => [['name' => 'Custom item']]]]],
        ],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal')
            ->where('unlocked', true)
            ->has('planDays.1.meals')
            ->has('planDays.2.meals')
            ->missing('planDays.3'));
});

test('locked members only see day one of their own plan', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Two days',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['name' => 'Oats']]]]],
            ['meals' => [['meal_type' => 'dinner', 'items' => [['name' => 'Rice']]]]],
        ],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('planDays.1.meals')
            ->missing('planDays.2'));
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

test('members can view a meal detail page with their stored item names', function () {
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

test('meal items expose only the stored name and portion note', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $meal = MealPlanMeal::firstOrFail();

    $response = $this->actingAs($user)
        ->get(route('member.meals.show', $meal->id))
        ->assertOk();

    $item = collect($response->viewData('page')['props']['meal']['items'])->first();

    expect($item['name'])->not->toBeEmpty()
        ->and($item)->toHaveKeys(['item_id', 'name', 'portion_note'])
        ->and($item)->not->toHaveKeys(['food_id', 'swap_options', 'macros']);
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
