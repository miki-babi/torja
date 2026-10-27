<?php

use App\Models\Food;
use App\Models\MealPlanItem;
use App\Models\MealPlanItemOverride;
use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mealWithSwap(): array
{
    $food = Food::factory()->create(['name_en' => 'Original']);
    $swap = Food::factory()->create(['name_en' => 'Alternative']);
    $meal = MealPlanMeal::factory()->create(['day' => 1]);
    $item = MealPlanItem::factory()->for($meal, 'meal')->for($food)->create();
    $meal->swaps()->attach($swap->id, ['reason' => 'lighter', 'position' => 0]);

    return [$food, $swap, $meal, $item];
}

test('member can pick a swap and it persists', function () {
    [$food, $swap, $meal, $item] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $response = $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $swap->id],
    );

    $response->assertOk();
    expect($response->json('meal.items.0.name'))->toBe('Alternative')
        ->and($response->json('meal.items.0.chosen'))->toBeTrue()
        ->and(MealPlanItemOverride::query()->where('user_id', $user->id)->where('food_id', $swap->id)->exists())->toBeTrue();
});

test('swap options include reason and excluded flags', function () {
    [, $swap, $meal, $item] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $response = $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $swap->id],
    );

    expect($response->json('meal.items.0.swap_options.0.name'))->toBe('Alternative')
        ->and($response->json('meal.items.0.swap_options.0.reason'))->toBe('lighter')
        ->and($response->json('meal.items.0.swap_options.0.excluded'))->toBeFalse()
        ->and($response->json('meal.items.0.swap_options.0.macros'))
        ->toHaveKeys(['kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g']);
});

test('items expose the original food id and image', function () {
    [$food, $swap, $meal, $item] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $response = $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $swap->id],
    );

    $response->assertOk();
    expect($response->json('meal.items.0.original_food_id'))->toBe($food->id)
        ->and($response->json('meal.items.0.original_name'))->toBe('Original')
        ->and($response->json('meal.items.0'))->toHaveKey('original_image_url');
});

test('a food outside the allowed swaps is rejected', function () {
    [, , $meal, $item] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    $intruder = Food::factory()->create();

    $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $intruder->id],
    )->assertStatus(422);
});

test('an item from another meal is rejected', function () {
    [, , $meal] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    $otherItem = MealPlanItem::factory()->create();

    $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $otherItem]),
        ['food_id' => $otherItem->food_id],
    )->assertNotFound();
});

test('locked days cannot be swapped', function () {
    [$food, $swap] = mealWithSwap();
    $meal = MealPlanMeal::factory()->create(['day' => 3]);
    $item = MealPlanItem::factory()->for($meal, 'meal')->for($food)->create();
    $meal->swaps()->attach($swap->id, ['reason' => 'x', 'position' => 0]);
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $swap->id],
    )->assertForbidden();
});

test('delete restores the original item', function () {
    [$food, $swap, $meal, $item] = mealWithSwap();
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    MealPlanItemOverride::factory()
        ->state(['user_id' => $user->id, 'meal_plan_item_id' => $item->id, 'food_id' => $swap->id])
        ->create();

    $response = $this->actingAs($user)->deleteJson(
        route('member.meals.items.swap.destroy', [$meal, $item]),
    );

    $response->assertOk();
    expect($response->json('meal.items.0.name'))->toBe('Original')
        ->and($response->json('meal.items.0.chosen'))->toBeFalse()
        ->and(MealPlanItemOverride::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('overrides do not leak across users', function () {
    [, $swap, $meal, $item] = mealWithSwap();
    $owner = User::factory()->create();
    NutritionProfile::factory()->forUser($owner)->completed()->create();
    MealPlanItemOverride::factory()
        ->state(['user_id' => $owner->id, 'meal_plan_item_id' => $item->id, 'food_id' => $swap->id])
        ->create();

    $viewer = User::factory()->create();
    NutritionProfile::factory()->forUser($viewer)->completed()->create();

    $this->actingAs($viewer)->putJson(
        route('member.meals.items.swap', [$meal, $item]),
        ['food_id' => $swap->id],
    )->assertOk();

    expect(MealPlanItemOverride::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and(MealPlanItemOverride::query()->where('user_id', $viewer->id)->count())->toBe(1);
});
