<?php

use App\Models\Habit;
use App\Models\User;
use App\Models\WorkoutSession;
use Inertia\Testing\AssertableInertia as Assert;

test('streak is zero with no activity', function () {
    $user = User::factory()->create();

    expect($user->activityStreak())->toBe(0);
});

test('a workout session counts toward the streak', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->for($user)->create(['date' => today()]);

    expect($user->activityStreak())->toBe(1);
});

test('a habit log counts toward the streak', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    $habit->logs()->create(['date' => today()->subDays(2)]);
    $habit->logs()->create(['date' => today()->subDay()]);

    // Today is still pending, so the streak counts back from yesterday.
    expect($user->activityStreak())->toBe(2);
});

test('workout and habit on the same day count once', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    WorkoutSession::factory()->for($user)->create(['date' => today()]);
    $habit->logs()->create(['date' => today()]);

    expect($user->activityStreak())->toBe(1);
});

test('a gap day resets the streak', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->for($user)->create(['date' => today()]);
    WorkoutSession::factory()->for($user)->create(['date' => today()->subDays(3)]);

    expect($user->activityStreak())->toBe(1);
});

test('other users activity does not count', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->create(['date' => today()]);
    Habit::factory()->create()->logs()->create(['date' => today()]);

    expect($user->activityStreak())->toBe(0);
});

test('activity week flags the last seven days', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->for($user)->create(['date' => today()]);
    WorkoutSession::factory()->for($user)->create(['date' => today()->subDays(6)]);

    $week = $user->activityWeek();

    expect($week)->toHaveCount(7)
        ->and($week[6]['date'])->toBe(today()->toDateString())
        ->and($week[6]['active'])->toBeTrue()
        ->and($week[0]['active'])->toBeTrue()
        ->and(collect($week)->where('active', true))->toHaveCount(2);
});

test('home and profile pages include the streak prop', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->for($user)->create(['date' => today()]);
    $this->actingAs($user);

    $this->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->where('streak', 1));

    $this->get(route('member.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/profile')
            ->where('streak', 1)
            ->has('activityWeek', 7)
            ->where('activityWeek.6.active', true));
});
