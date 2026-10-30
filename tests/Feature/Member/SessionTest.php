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

test('quick logging a set creates and resumes todays free session', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'barbell-bench-press',
        'weight_kg' => 60,
        'reps' => 10,
    ])->assertCreated()
        ->assertJsonCount(1, 'session.sets')
        ->assertJsonPath('session.sets.0.weight_kg', 60)
        ->assertJsonPath('session.sets.0.set_index', 0);

    // A second log lands in the same session with the next set index.
    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'barbell-bench-press',
        'weight_kg' => 70,
        'reps' => 8,
    ])->assertCreated()
        ->assertJsonCount(2, 'session.sets')
        ->assertJsonPath('session.sets.1.set_index', 1);

    expect($user->workoutSessions()->count())->toBe(1);
    expect($user->workoutSessions()->first()->exercise_playlist_id)->toBeNull();
});

test('quick logging appends after a gap left by a deleted set', function () {
    Exercise::factory()->create(['slug' => 'push-up']);
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create(['date' => now()->toDateString()]);
    // Sets at indexes 0 and 2 — index 1 was deleted, leaving a gap.
    $session->sets()->create(['exercise_id' => 'push-up', 'set_index' => 0, 'reps' => 10]);
    $session->sets()->create(['exercise_id' => 'push-up', 'set_index' => 2, 'reps' => 10]);
    $this->actingAs($user);

    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'push-up',
        'reps' => 12,
    ])->assertCreated()
        ->assertJsonPath('session.sets.2.set_index', 3);
});

test('quick logging allows a missing weight but requires reps', function () {
    Exercise::factory()->create(['slug' => 'push-up']);
    $user = User::factory()->create();
    $this->actingAs($user);

    // Bodyweight exercise: no weight is fine.
    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'push-up',
        'reps' => 12,
    ])->assertCreated()
        ->assertJsonPath('session.sets.0.weight_kg', null)
        ->assertJsonPath('session.sets.0.reps', 12);

    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'push-up',
        'weight_kg' => 10,
    ])->assertUnprocessable();

    $this->postJson(route('member.sessions.log-set'), [
        'exercise_id' => 'not-a-move',
        'reps' => 10,
    ])->assertUnprocessable();
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
            ->has('backUrl')
            ->has('bodyParts')
            ->has('equipment'));
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
