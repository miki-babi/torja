<?php

use App\Models\ExercisePlaylist;
use App\Models\NutritionProfile;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomeBanners;
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

test('home exposes the configured ad banners', function () {
    Setting::set(HomeBanners::SETTING_KEY, [
        'banners' => [
            ['image' => 'https://cdn.example.com/a.jpg', 'url' => 'https://example.com/offer', 'cta' => 'Shop now'],
            ['image' => 'home-banners/b.jpg', 'url' => '/pro', 'cta' => 'Go Pro'],
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('banners.0.image', 'https://cdn.example.com/a.jpg')
            ->where('banners.0.url', 'https://example.com/offer')
            ->where('banners.0.cta', 'Shop now')
            ->where('banners.0.external', true)
            ->where('banners.1.url', '/pro')
            ->where('banners.1.external', false));
});

test('home exposes an empty banner list by default', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('banners', []));
});
