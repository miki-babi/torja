<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use App\Support\HomeBanners;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.home'))->assertRedirect(route('login'));
});

test('home shows the member name and zeroed week stats by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->where('userName', Str::before($user->name, ' '))
            ->where('weekStats.sessions', 0)
            ->where('weekStats.volume', 0));
});

test('home week stats count this week\'s logged sessions and volume', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
    ]);
    WorkoutSet::factory()->create([
        'workout_session_id' => $session->id,
        'exercise_id' => 'squat',
        'weight_kg' => 80,
        'reps' => 5,
    ]);
    WorkoutSession::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subWeek()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('weekStats.sessions', 1)
            ->where('weekStats.volume', 400));
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
