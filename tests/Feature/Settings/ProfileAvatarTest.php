<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile photo can be uploaded', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

    $user->refresh();

    $response
        ->assertOk()
        ->assertJson(['avatar' => $user->avatar]);

    expect($user->avatar_path)->toStartWith("avatars/{$user->id}/");
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('uploading a new photo replaces the previous file', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.avatar.store'), [
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $oldPath = $user->refresh()->avatar_path;

    $this->actingAs($user)->post(route('profile.avatar.store'), [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($user->refresh()->avatar_path);
});

test('profile photo can be removed', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->updateAvatar(UploadedFile::fake()->image('me.jpg'));

    $path = $user->avatar_path;

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.avatar.destroy'));

    $response
        ->assertOk()
        ->assertJson(['avatar' => null]);

    Storage::disk('public')->assertMissing($path);
    expect($user->refresh()->avatar_path)->toBeNull();
});

test('profile photo must be an image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->create('notes.pdf', 10),
        ]);

    $response->assertSessionHasErrors('avatar');
    expect($user->refresh()->avatar_path)->toBeNull();
});

test('deleting the account removes the stored photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->updateAvatar(UploadedFile::fake()->image('me.jpg'));

    $path = $user->avatar_path;

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    Storage::disk('public')->assertMissing($path);
});

test('guests cannot manage profile photos', function () {
    Storage::fake('public');

    $this
        ->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect(route('login'));

    $this
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('login'));
});
