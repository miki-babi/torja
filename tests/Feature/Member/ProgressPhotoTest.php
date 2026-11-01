<?php

use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot upload progress photos', function () {
    $this->postJson(route('member.progress-photos.store'), [])
        ->assertUnauthorized();
});

test('members can upload a progress photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('member.progress-photos.store'),
        ['photo' => UploadedFile::fake()->image('progress.jpg', 400, 400)],
    );

    $response->assertCreated();

    $photo = ProgressPhoto::where('user_id', $user->id)->sole();

    Storage::disk('public')->assertExists($photo->path);
    expect($response->json('photo.taken_on'))
        ->toBe(today()->toDateString());
});

test('non-image uploads are rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(
        route('member.progress-photos.store'),
        ['photo' => UploadedFile::fake()->create('notes.pdf', 100)],
    )->assertUnprocessable();
});

test('members can only delete their own photos', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $own = ProgressPhoto::create([
        'user_id' => $owner->id,
        'path' => 'progress-photos/x/a.jpg',
        'taken_on' => '2026-10-20',
    ]);
    $other = ProgressPhoto::create([
        'user_id' => User::factory()->create()->id,
        'path' => 'progress-photos/y/b.jpg',
        'taken_on' => '2026-10-20',
    ]);
    Storage::disk('public')->put($own->path, 'img');

    $this->actingAs($owner)
        ->deleteJson(route('member.progress-photos.destroy', $other->id))
        ->assertForbidden();

    $this->actingAs($owner)
        ->deleteJson(route('member.progress-photos.destroy', $own->id))
        ->assertOk();

    expect(ProgressPhoto::find($own->id))->toBeNull();
    Storage::disk('public')->assertMissing($own->path);
});
