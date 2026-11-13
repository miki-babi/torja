<?php

use App\Models\Food;
use App\Models\MealPlan;
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
