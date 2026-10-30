<?php

use App\Models\ExercisePlaylist;
use App\Models\NutritionProfile;
use App\Models\User;
use Database\Seeders\FoodDatabaseSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.home'))->assertRedirect(route('login'));
});

test('members see their plan day and workout on home', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->where('userName', Str::before($user->name, ' '))
            ->has('planDay.meals')
            ->has('todayWorkout'));
});

test('home generates todays workout playlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('todayWorkout.title')
            ->has('todayWorkout.muscle')
            ->has('todayWorkout.exerciseCount')
            ->has('todayWorkout.playUrl'));

    expect(ExercisePlaylist::where('user_id', $user->id)
        ->whereDate('generated_for', today())
        ->count())->toBe(1);
});
