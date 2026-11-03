<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.history'))->assertRedirect(route('login'));
});

test('history page renders the member sessions', function () {
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

    $this->actingAs($user)
        ->get(route('member.history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/history')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id)
            ->where('sessions.0.has_recap', false)
            ->has('exerciseSummaries.squat'));
});
