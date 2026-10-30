<?php

use App\Models\NutritionProfile;
use App\Models\User;
use Database\Seeders\FoodDatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.profile'))->assertRedirect(route('login'));
});

test('members see their account info, stats and targets', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('member.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/profile')
            ->where('account.name', $user->name)
            ->where('account.email', $user->email)
            ->where('isPro', false)
            ->where('proCard.title', 'TrainPro Pro')
            ->where('proCard.button', 'Go Pro')
            ->where('profileCompleted', true)
            ->has('body')
            ->has('goal')
            ->has('targets')
            ->has('planName'));
});

test('pro members see the pro state card', function () {
    $this->actingAs(proUser())
        ->get(route('member.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isPro', true)
            ->where('proCard.title', "You're Pro")
            ->where('proCard.button', null));
});

test('incomplete profiles still load with null targets', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('member.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileCompleted', false)
            ->where('targets', null));
});
