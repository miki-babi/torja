<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\ExerciseProgram;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage playlists', function () {
    $this->postJson(route('member.playlists.store'), ['name' => 'Push'])
        ->assertUnauthorized();
});

test('user can create, rename and delete a playlist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('member.playlists.store'), ['name' => 'Push day']);
    $response->assertCreated()->assertJsonPath('playlist.name', 'Push day');
    $id = $response->json('playlist.id');

    $this->patchJson(route('member.playlists.update', $id), ['name' => 'Pull day'])
        ->assertOk()
        ->assertJsonPath('playlist.name', 'Pull day');

    $this->deleteJson(route('member.playlists.destroy', $id))->assertOk();
    expect(ExercisePlaylist::find($id))->toBeNull();
});

test('user can add and remove exercises from a playlist', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    $playlist = ExercisePlaylist::factory()->create();
    $this->actingAs($playlist->user);

    $this->postJson(route('member.playlists.exercises.store', $playlist->id), [
        'exercise_id' => 'barbell-bench-press',
    ])->assertOk()->assertJsonPath('playlist.exercise_ids.0', 'barbell-bench-press');

    // Adding the same exercise again stays deduplicated.
    $this->postJson(route('member.playlists.exercises.store', $playlist->id), [
        'exercise_id' => 'barbell-bench-press',
    ])->assertOk()->assertJsonCount(1, 'playlist.exercise_ids');

    // Legacy catalog ids resolve to the same exercise.
    $this->postJson(route('member.playlists.exercises.store', $playlist->id), [
        'exercise_id' => 'bench-press',
    ])->assertOk()->assertJsonCount(1, 'playlist.exercise_ids');

    $this->deleteJson(route('member.playlists.exercises.destroy', [
        'playlist' => $playlist->id,
        'exercise' => 'barbell-bench-press',
    ]))->assertOk()->assertJsonCount(0, 'playlist.exercise_ids');
});

test('unknown exercises cannot be added to a playlist', function () {
    $playlist = ExercisePlaylist::factory()->create();
    $this->actingAs($playlist->user);

    $this->postJson(route('member.playlists.exercises.store', $playlist->id), [
        'exercise_id' => 'not-a-move',
    ])->assertUnprocessable();
});

test('users cannot touch other users playlists', function () {
    $other = ExercisePlaylist::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->patchJson(route('member.playlists.update', $other->id), ['name' => 'x'])
        ->assertForbidden();
    $this->deleteJson(route('member.playlists.destroy', $other->id))
        ->assertForbidden();
    $this->postJson(route('member.playlists.exercises.store', $other->id), [
        'exercise_id' => 'barbell-bench-press',
    ])->assertForbidden();
});

test('members can view their own playlist page', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    $playlist = ExercisePlaylist::factory()->create();
    $playlist->items()->create([
        'exercise_id' => 'barbell-bench-press',
        'position' => 1,
    ]);

    $this->actingAs($playlist->user)
        ->get(route('member.playlists.show', $playlist->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/playlist-show')
            ->where('playlist.id', $playlist->id)
            ->has('sessions')
            ->has('exerciseSummaries.barbell-bench-press.image_url'));
});

test('members cannot view other users playlist pages', function () {
    $other = ExercisePlaylist::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('member.playlists.show', $other->id))
        ->assertForbidden();
});

test('members can open the workout player for their playlist', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    Exercise::factory()->create(['slug' => 'squat']);
    $playlist = ExercisePlaylist::factory()->create();
    $playlist->items()->create(['exercise_id' => 'barbell-bench-press', 'position' => 1]);
    $playlist->items()->create(['exercise_id' => 'squat', 'position' => 2]);

    $this->actingAs($playlist->user)
        ->get(route('member.playlists.play', $playlist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/workout-player')
            ->where('workout.id', $playlist->id)
            ->has('workout.exercises', 2)
            ->has('workout.exercises.0.gif_url')
            ->has('session')
            ->has('backUrl'));

    expect($playlist->user->workoutSessions()->sole()->exercise_playlist_id)
        ->toBe($playlist->id);
});

test('the playlist player resumes today\'s session instead of duplicating it', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    $playlist = ExercisePlaylist::factory()->create();
    $playlist->items()->create(['exercise_id' => 'squat', 'position' => 1]);

    $this->actingAs($playlist->user)->get(route('member.playlists.play', $playlist));
    $this->actingAs($playlist->user)->get(route('member.playlists.play', $playlist));

    expect($playlist->user->workoutSessions()->count())->toBe(1);
});

test('the playlist player uses set and rep targets from the source program workout', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    $program = ExerciseProgram::factory()->create();
    $workout = $program->workouts()->create(['title' => 'Legs', 'position' => 0]);
    $workout->items()->create([
        'exercise_id' => 'squat',
        'position' => 0,
        'sets' => 4,
        'reps' => 8,
    ]);

    $playlist = ExercisePlaylist::factory()->create([
        'exercise_program_id' => $program->id,
        'exercise_program_workout_id' => $workout->id,
    ]);
    $playlist->items()->create(['exercise_id' => 'squat', 'position' => 1]);

    $this->actingAs($playlist->user)
        ->get(route('member.playlists.play', $playlist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workout.title', 'Legs')
            ->where('workout.exercises.0.sets', 4)
            ->where('workout.exercises.0.reps', 8));
});

test('members cannot open the workout player for other users playlists', function () {
    $other = ExercisePlaylist::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('member.playlists.play', $other))
        ->assertForbidden();
});

test('empty playlists redirect to the today page instead of the player', function () {
    $playlist = ExercisePlaylist::factory()->create();

    $this->actingAs($playlist->user)
        ->get(route('member.playlists.play', $playlist))
        ->assertRedirect(route('member.today'));
});
