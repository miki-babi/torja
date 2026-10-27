<?php

use App\Models\Food;
use App\Models\MealPlan;
use App\Models\MealPlanItemOverride;
use App\Models\MealProgram;
use App\Models\User;
use App\Services\Programs\MealProgramAccessGranter;
use Inertia\Testing\AssertableInertia as Assert;

function claimedMealPlan(User $user): MealPlan
{
    $injera = Food::factory()->create(['name_en' => 'Injera']);
    $shiro = Food::factory()->create(['name_en' => 'Shiro']);

    $program = MealProgram::factory()->create(['name' => 'Claimed Plan']);

    $program->meals()->create(['day' => 1, 'meal_type' => 'breakfast', 'position' => 0])
        ->items()->create(['food_id' => $injera->id, 'position' => 0]);

    $program->meals()->create(['day' => 3, 'meal_type' => 'dinner', 'position' => 0])
        ->items()->create(['food_id' => $shiro->id, 'portion_note' => '1 cup', 'position' => 0]);

    app(MealProgramAccessGranter::class)->grant($user, $program);

    return MealPlan::where('user_id', $user->id)->sole();
}

test('members can view all days of their claimed meal program', function () {
    $user = User::factory()->create();
    $plan = claimedMealPlan($user);

    $this->actingAs($user)
        ->get(route('member.meal-programs.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal-program')
            ->where('plan.name', 'Claimed Plan')
            ->has('days', 2)
            ->where('days.3.meals.0.type', 'dinner'));
});

test('members cannot view another user\'s meal plan copy', function () {
    $plan = claimedMealPlan(User::factory()->create());
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('member.meal-programs.show', $plan))
        ->assertNotFound();
});

test('meals on an owned plan copy open for every day without pro', function () {
    $user = User::factory()->create();
    $plan = claimedMealPlan($user);

    expect($user->isPro())->toBeFalse();

    $dayThreeMeal = $plan->meals()->where('day', 3)->sole();

    $this->actingAs($user)
        ->get(route('member.meals.show', $dayThreeMeal))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal-show')
            ->where('planName', 'Claimed Plan')
            ->where('day', 3));
});

test('meals on an owned plan copy support swaps without pro', function () {
    $user = User::factory()->create();
    $plan = claimedMealPlan($user);

    $meal = $plan->meals()->where('day', 3)->sole();
    $item = $meal->items()->sole();
    $swap = Food::factory()->create(['name_en' => 'Teff bread']);
    $item->food->swaps()->attach($swap->id, ['reason' => 'lighter', 'position' => 0]);

    $this->actingAs($user)
        ->putJson(route('member.meals.items.swap', [$meal, $item]), [
            'food_id' => $swap->id,
        ])
        ->assertOk()
        ->assertJsonPath('meal.items.0.name', 'Teff bread');

    expect(MealPlanItemOverride::sole()->food_id)->toBe($swap->id);
});

test('global plans keep their day-one gate for locked members', function () {
    $globalPlan = MealPlan::factory()->create(['user_id' => null]);
    $lockedMeal = $globalPlan->meals()->create(['day' => 4, 'meal_type' => 'dinner', 'position' => 0]);

    $this->actingAs(User::factory()->create())
        ->get(route('member.meals.show', $lockedMeal))
        ->assertForbidden();
});

test('one member cannot open meals from another member\'s copy', function () {
    $plan = claimedMealPlan(User::factory()->create());
    $meal = $plan->meals()->where('day', 3)->sole();

    $this->actingAs(User::factory()->create())
        ->get(route('member.meals.show', $meal))
        ->assertForbidden();
});
