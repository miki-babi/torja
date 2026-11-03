<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.records'))->assertRedirect(route('login'));
});

test('members can view the personal records page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.records'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/records')
            ->has('sessions')
            ->has('exerciseSummaries')
            ->has('bodyParts'));
});

test('records page returns the member\'s session history with sets', function () {
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

    // Another member's session must not leak into the payload.
    WorkoutSession::factory()->create(['date' => now()->toDateString()]);

    $this->actingAs($user)
        ->get(route('member.records'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/records')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id)
            ->has('sessions.0.sets', 1)
            ->where('sessions.0.sets.0.exercise_id', 'squat')
            ->where('exerciseSummaries.squat.name', fn ($name) => is_string($name)));
});
