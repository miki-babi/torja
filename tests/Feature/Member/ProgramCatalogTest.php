<?php

use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\User;
use App\Services\Programs\MealProgramAccessGranter;
use App\Services\Programs\ProgramAccessGranter;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can browse published exercise and meal programs', function () {
    ExerciseProgram::factory()->published()->create(['name' => 'Home Split']);
    MealProgram::factory()->published()->create(['name' => 'Cutting Plan']);

    $this->get(route('programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/programs')
            ->has('exercisePrograms', 1)
            ->has('mealPrograms', 1)
            ->where('exercisePrograms.0.name', 'Home Split')
            ->where('exercisePrograms.0.claimed', false)
            ->where('mealPrograms.0.name', 'Cutting Plan')
            ->where('mealPrograms.0.claimed', false)
            ->where('isPro', false));
});

test('only published and shared programs are listed', function () {
    ExerciseProgram::factory()->published()->create(['name' => 'Listed']);
    ExerciseProgram::factory()->create(['name' => 'Draft']);
    ExerciseProgram::factory()->published()->unshared()->create(['name' => 'Unshared']);
    MealProgram::factory()->published()->create(['name' => 'Listed Meal']);
    MealProgram::factory()->create(['name' => 'Draft Meal']);
    MealProgram::factory()->published()->unshared()->create(['name' => 'Unshared Meal']);

    $this->get(route('programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('exercisePrograms', 1)
            ->has('mealPrograms', 1)
            ->where('exercisePrograms.0.name', 'Listed')
            ->where('mealPrograms.0.name', 'Listed Meal'));
});

test('the catalog can be searched by name', function () {
    ExerciseProgram::factory()->published()->create(['name' => 'Strength Basics']);
    ExerciseProgram::factory()->published()->create(['name' => 'Yoga Flow']);
    MealProgram::factory()->published()->create(['name' => 'Strength Fuel']);
    MealProgram::factory()->published()->create(['name' => 'Bulk Plan']);

    $this->get(route('programs.index', ['q' => 'strength']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('exercisePrograms', 1)
            ->has('mealPrograms', 1)
            ->where('exercisePrograms.0.name', 'Strength Basics')
            ->where('mealPrograms.0.name', 'Strength Fuel')
            ->where('filters.q', 'strength'));
});

test('members see which programs they already claimed', function () {
    $exerciseProgram = ExerciseProgram::factory()->published()->create();
    $mealProgram = MealProgram::factory()->published()->create();
    $user = proUser();

    app(ProgramAccessGranter::class)->grant($user, $exerciseProgram);
    app(MealProgramAccessGranter::class)->grant($user, $mealProgram);

    $this->actingAs($user)
        ->get(route('programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exercisePrograms.0.claimed', true)
            ->where('mealPrograms.0.claimed', true)
            ->where('isPro', true));
});

test('non-pro members see programs as unclaimed without pro', function () {
    ExerciseProgram::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exercisePrograms.0.claimed', false)
            ->where('isPro', false));
});
