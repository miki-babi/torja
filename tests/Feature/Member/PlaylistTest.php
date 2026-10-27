<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
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
    $playlist = ExercisePlaylist::factory()->create();

    $this->actingAs($playlist->user)
        ->get(route('member.playlists.show', $playlist->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/playlist-show')
            ->where('playlist.id', $playlist->id)
            ->has('sessions'));
});

test('members cannot view other users playlist pages', function () {
    $other = ExercisePlaylist::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('member.playlists.show', $other->id))
        ->assertForbidden();
});
