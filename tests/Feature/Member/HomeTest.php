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

test('home shows no meal plan until the member builds one', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->where('userName', Str::before($user->name, ' '))
            ->where('planDay', null));
});

test('home shows the member\'s own plan day once built', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();

    $this->actingAs($user)->postJson(route('member.meal-plans.store'), [
        'name' => 'My plan',
        'days' => [
            ['meals' => [['meal_type' => 'breakfast', 'items' => [['name' => 'Oats']]]]],
        ],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->has('planDay.meals'));
});

test('home does not generate a workout playlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk();

    expect(ExercisePlaylist::where('user_id', $user->id)->count())->toBe(0);
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
