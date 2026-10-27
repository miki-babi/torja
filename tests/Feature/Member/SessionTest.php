<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use App\Models\WorkoutSession;
use Inertia\Testing\AssertableInertia;

test('guests cannot manage sessions', function () {
    $this->postJson(route('member.sessions.store'))->assertUnauthorized();
});

test('starting a session is idempotent per playlist and day', function () {
    $user = User::factory()->create();
    $playlist = ExercisePlaylist::factory()->for($user)->create();
    $this->actingAs($user);

    $first = $this->postJson(route('member.sessions.store'), [
        'exercise_playlist_id' => $playlist->id,
    ])->assertCreated()->json('session.id');

    $second = $this->postJson(route('member.sessions.store'), [
        'exercise_playlist_id' => $playlist->id,
    ])->json('session.id');

    expect($second)->toBe($first);
    expect($user->workoutSessions()->count())->toBe(1);
});

test('sets can be upserted and removed', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create();
    $this->actingAs($user);

    $this->putJson(route('member.sessions.sets.upsert', $session->id), [
        'exercise_id' => 'barbell-bench-press',
        'set_index' => 0,
        'weight_kg' => 60,
        'reps' => 10,
    ])->assertOk()->assertJsonCount(1, 'session.sets');

    // Upserting the same index updates instead of duplicating.
    $this->putJson(route('member.sessions.sets.upsert', $session->id), [
        'exercise_id' => 'barbell-bench-press',
        'set_index' => 0,
        'weight_kg' => 70,
        'reps' => 8,
    ])->assertOk()
        ->assertJsonCount(1, 'session.sets')
        ->assertJsonPath('session.sets.0.weight_kg', 70);

    // Legacy catalog ids resolve to the canonical slug.
    $this->putJson(route('member.sessions.sets.upsert', $session->id), [
        'exercise_id' => 'bench-press',
        'set_index' => 1,
        'weight_kg' => 70,
        'reps' => 6,
    ])->assertOk()->assertJsonCount(2, 'session.sets');

    $setId = $session->sets()->first()->id;
    $this->deleteJson(route('member.sessions.sets.destroy', [
        'session' => $session->id,
        'set' => $setId,
    ]))->assertOk()->assertJsonCount(1, 'session.sets');
});

test('users cannot modify other users sessions', function () {
    $session = WorkoutSession::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->putJson(route('member.sessions.sets.upsert', $session->id), [
        'exercise_id' => 'barbell-bench-press',
        'set_index' => 0,
        'weight_kg' => 60,
        'reps' => 10,
    ])->assertForbidden();
});

test('members can view the add-exercise page for their own session', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('member.sessions.exercises', $session->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('member/session-add-exercise')
            ->where('session.id', $session->id)
            ->has('backUrl'));
});

test('users cannot view the add-exercise page for other users sessions', function () {
    $session = WorkoutSession::factory()->create();
    $this->actingAs(User::factory()->create())
        ->get(route('member.sessions.exercises', $session->id))
        ->assertForbidden();
});

test('unknown exercises cannot be logged', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create();
    $this->actingAs($user);

    $this->putJson(route('member.sessions.sets.upsert', $session->id), [
        'exercise_id' => 'not-a-move',
        'set_index' => 0,
        'weight_kg' => 60,
        'reps' => 10,
    ])->assertUnprocessable();
});
