<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sharedPlaylist(array $attributes = []): ExercisePlaylist
{
    $playlist = ExercisePlaylist::factory()->create($attributes);
    $playlist->items()->createMany([
        ['exercise_id' => 'barbell-bench-press', 'position' => 1],
        ['exercise_id' => 'push-up', 'position' => 2],
    ]);

    return $playlist;
}

test('owner can share a custom playlist and the link is stable', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    Exercise::factory()->create(['slug' => 'push-up']);
    $playlist = sharedPlaylist();
    $this->actingAs($playlist->user);

    $url = $this->postJson(route('member.playlists.share', $playlist->id))
        ->assertOk()
        ->json('share_url');

    $this->get($url)->assertRedirectContains('shared/playlists/');
    expect($playlist->refresh()->share_token)->not->toBeNull();

    // Sharing again reuses the same token.
    $this->postJson(route('member.playlists.share', $playlist->id))
        ->assertOk()
        ->assertJsonPath('share_url', $url);
});

test('daily generated playlists cannot be shared', function () {
    $playlist = sharedPlaylist(['generated_for' => today()]);
    $this->actingAs($playlist->user);

    $this->postJson(route('member.playlists.share', $playlist->id))
        ->assertForbidden();
});

test('users cannot share other users playlists', function () {
    $playlist = sharedPlaylist();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('member.playlists.share', $playlist->id))
        ->assertForbidden();
    $this->deleteJson(route('member.playlists.share.destroy', $playlist->id))
        ->assertForbidden();
});

test('guests cannot share playlists', function () {
    $playlist = sharedPlaylist();

    $this->postJson(route('member.playlists.share', $playlist->id))
        ->assertUnauthorized();
});

test('unshare revokes the public link', function () {
    $playlist = sharedPlaylist();
    $this->actingAs($playlist->user);

    $url = $this->postJson(route('member.playlists.share', $playlist->id))
        ->json('share_url');

    $this->deleteJson(route('member.playlists.share.destroy', $playlist->id))
        ->assertOk();

    $this->get($url)->assertNotFound();
});

test('guests can view a shared playlist', function () {
    Exercise::factory()->create([
        'slug' => 'barbell-bench-press',
        'name' => 'barbell bench press',
    ]);
    Exercise::factory()->create(['slug' => 'push-up', 'name' => 'push-up']);
    $playlist = sharedPlaylist();
    $playlist->update(['share_token' => 'token-1234']);

    $this->get(route('playlists.shared.show', 'token-1234'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/shared-playlist')
            ->where('playlist.name', $playlist->name)
            ->where('playlist.owner', $playlist->user->name)
            ->has('playlist.exercises', 2)
            ->where('isOwner', false)
            ->has('saveUrl'));
});

test('unknown share tokens return 404', function () {
    $this->get(route('playlists.shared.show', 'not-a-token'))
        ->assertNotFound();
});

test('owner viewing their shared link is flagged as owner', function () {
    $playlist = sharedPlaylist();
    $playlist->update(['share_token' => 'token-1234']);

    $this->actingAs($playlist->user)
        ->get(route('playlists.shared.show', 'token-1234'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwner', true));
});

test('members can save a copy of a shared playlist', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    Exercise::factory()->create(['slug' => 'push-up']);
    $playlist = sharedPlaylist();
    $playlist->update(['share_token' => 'token-1234']);
    $recipient = User::factory()->create();

    $response = $this->actingAs($recipient)
        ->postJson(route('playlists.shared.save', 'token-1234'))
        ->assertCreated();

    $copy = ExercisePlaylist::findOrFail($response->json('playlist_id'));

    expect($copy->user_id)->toBe($recipient->id)
        ->and($copy->name)->toBe($playlist->name)
        ->and($copy->items->pluck('exercise_id')->all())
        ->toBe(['barbell-bench-press', 'push-up'])
        ->and($copy->items->pluck('position')->all())
        ->toBe([1, 2]);

    // The original is untouched.
    expect($playlist->items()->count())->toBe(2);
});

test('owner saving their own link gets their playlist back', function () {
    $playlist = sharedPlaylist();
    $playlist->update(['share_token' => 'token-1234']);

    $response = $this->actingAs($playlist->user)
        ->postJson(route('playlists.shared.save', 'token-1234'))
        ->assertOk();

    expect($response->json('playlist_id'))->toBe($playlist->id)
        ->and(ExercisePlaylist::count())->toBe(1);
});

test('guests cannot save a shared playlist', function () {
    $playlist = sharedPlaylist();
    $playlist->update(['share_token' => 'token-1234']);

    $this->postJson(route('playlists.shared.save', 'token-1234'))
        ->assertUnauthorized();
});
