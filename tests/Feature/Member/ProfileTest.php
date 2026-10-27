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
            ->where('profileCompleted', true)
            ->has('body')
            ->has('goal')
            ->has('targets')
            ->has('planName'));
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
