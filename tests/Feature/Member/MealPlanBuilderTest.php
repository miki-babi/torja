<?php

use App\Models\MealProgram;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the builder page lists published meal programs for discovery', function () {
    MealProgram::factory()->published()->create(['name' => 'Cutting Plan']);
    MealProgram::factory()->create(['name' => 'Draft Plan']);
    MealProgram::factory()->published()->unshared()->create(['name' => 'Unshared Plan']);

    $this->actingAs(User::factory()->create())
        ->get(route('member.meal-plans.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/meal-plan-builder')
            ->has('mealPrograms', 1)
            ->where('mealPrograms.0.name', 'Cutting Plan')
            ->where('mealPrograms.0.kind', 'meal'));
});
