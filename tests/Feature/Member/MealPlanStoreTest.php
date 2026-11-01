<?php

use App\Models\Food;
use App\Models\MealPlan;
use App\Models\User;

test('store creates a personal meal plan with meals and items', function () {
    $food = Food::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'My week',
        'days' => [
            ['meals' => [
                ['meal_type' => 'breakfast', 'items' => [
                    ['food_id' => $food->id],
                    ['name' => 'Black coffee'],
                ]],
                ['meal_type' => 'dinner', 'items' => [
                    ['food_id' => $food->id, 'portion_note' => '2 cups'],
                ]],
            ]],
            ['meals' => [
                ['meal_type' => 'lunch', 'items' => [['name' => 'Leftovers']]],
            ]],
        ],
    ])->assertCreated();

    $plan = MealPlan::where('user_id', $user->id)->sole();

    $breakfast = $plan->meals()
        ->where('day', 1)
        ->where('meal_type', 'breakfast')
        ->sole();

    expect($plan->meals()->count())->toBe(3)
        ->and($plan->meals()->where('day', 2)->sole()->meal_type->value)->toBe('lunch')
        ->and($breakfast->items()->count())->toBe(2);

    $custom = $breakfast->items()->whereNull('food_id')->sole();

    expect($custom->raw_text)->toBe('Black coffee')
        ->and($custom->match_confidence->value)->toBe('none');
});

test('store rejects items with neither a food nor a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Empty item',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['portion_note' => 'x']]]]],
        ],
    ])->assertUnprocessable();

    expect(MealPlan::where('user_id', $user->id)->count())->toBe(0);
});

test('store rejects a duplicated meal type within one day', function () {
    $food = Food::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Dupes',
        'days' => [
            ['meals' => [
                ['meal_type' => 'breakfast', 'items' => [['food_id' => $food->id]]],
                ['meal_type' => 'breakfast', 'items' => [['food_id' => $food->id]]],
            ]],
        ],
    ])->assertUnprocessable();

    expect(MealPlan::where('user_id', $user->id)->count())->toBe(0);
});

test('store validates food ids, meal types and the plan name', function () {
    $food = Food::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Bad food',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['food_id' => 999999]]]]],
        ],
    ])->assertUnprocessable();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Bad type',
        'days' => [
            ['meals' => [['meal_type' => 'brunch', 'items' => [['food_id' => $food->id]]]]],
        ],
    ])->assertUnprocessable();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['food_id' => $food->id]]]]],
        ],
    ])->assertUnprocessable();
});
