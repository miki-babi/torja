<?php

use App\Models\MealPlan;
use App\Models\User;

test('store creates a personal meal plan with meals and items', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'My week',
        'days' => [
            ['meals' => [
                ['meal_type' => 'breakfast', 'items' => [
                    ['name' => 'Scrambled eggs'],
                    ['name' => 'Black coffee'],
                ]],
                ['meal_type' => 'dinner', 'items' => [
                    ['name' => 'Enjera with shiro', 'portion_note' => '2 rolls'],
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

    $item = $breakfast->items()->firstOrFail();

    expect($item->food_id)->toBeNull()
        ->and($item->raw_text)->toBe('Scrambled eggs')
        ->and($item->match_confidence->value)->toBe('none');
});

test('store rejects items with no name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Empty item',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['portion_note' => 'x']]]]],
        ],
    ])->assertUnprocessable();

    expect(MealPlan::where('user_id', $user->id)->count())->toBe(0);
});

test('store ignores submitted food ids', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Sneaky ids',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [
                ['name' => 'My oats', 'food_id' => 123],
            ]]]],
        ],
    ])->assertCreated();

    expect(MealPlan::where('user_id', $user->id)->sole()->meals()->sole()->items()->sole()->food_id)
        ->toBeNull();
});

test('store rejects a duplicated meal type within one day', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Dupes',
        'days' => [
            ['meals' => [
                ['meal_type' => 'breakfast', 'items' => [['name' => 'Eggs']]],
                ['meal_type' => 'breakfast', 'items' => [['name' => 'Toast']]],
            ]],
        ],
    ])->assertUnprocessable();

    expect(MealPlan::where('user_id', $user->id)->count())->toBe(0);
});

test('store validates meal types and the plan name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'Bad type',
        'days' => [
            ['meals' => [['meal_type' => 'brunch', 'items' => [['name' => 'Eggs']]]]],
        ],
    ])->assertUnprocessable();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['name' => 'Eggs']]]]],
        ],
    ])->assertUnprocessable();
});
