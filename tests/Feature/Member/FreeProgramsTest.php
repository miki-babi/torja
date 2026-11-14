<?php

use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Trainer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a member sees their coach free shared programs', function () {
    $member = User::factory()->create();
    $trainer = Trainer::factory()->create();
    $trainer->addClient($member);

    $free = ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Free bootcamp',
        'price_etb' => 0,
    ]);
    $freeMeal = MealProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Free meal plan',
        'price_etb' => 0,
    ]);

    ExerciseProgram::factory()->paid(500)->create([
        'user_id' => $trainer->user_id,
        'name' => 'Paid program',
    ]);
    ExerciseProgram::factory()->unshared()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Unshared free',
    ]);
    ExerciseProgram::factory()->create([
        'name' => 'Another coach freebie',
    ]);

    $this->actingAs($member)
        ->get(route('member.free-programs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/free-programs')
            ->has('coaches', 1)
            ->has('coaches.0.exercise', 1)
            ->where('coaches.0.exercise.0.name', 'Free bootcamp')
            ->where('coaches.0.exercise.0.url', route('programs.shared.show', $free->share_token))
            ->has('coaches.0.meal', 1)
            ->where('coaches.0.meal.0.name', 'Free meal plan')
            ->where('coaches.0.meal.0.url', route('meal-programs.shared.show', $freeMeal->share_token)));
});

test('a member whose coach has no free programs sees an empty catalog', function () {
    $member = User::factory()->create();

    // actingAs auto-links a coach with no programs — both lists are empty.
    $this->actingAs($member)
        ->get(route('member.free-programs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/free-programs')
            ->has('coaches', 1)
            ->has('coaches.0.exercise', 0)
            ->has('coaches.0.meal', 0));
});

test('guests cannot browse free programs', function () {
    $this->get(route('member.free-programs'))->assertRedirect(route('login'));
});
