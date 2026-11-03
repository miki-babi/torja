<?php

use App\Models\Exercise;
use App\Models\Recap;
use App\Models\RecapBravo;
use App\Models\RecapComment;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Exercise::factory()->create(['slug' => 'squat']);
    $this->user = User::factory()->create();
    $this->session = WorkoutSession::factory()->create([
        'user_id' => $this->user->id,
        'date' => now()->toDateString(),
        'finished_at' => now(),
    ]);
    WorkoutSet::factory()->create([
        'workout_session_id' => $this->session->id,
        'exercise_id' => 'squat',
        'weight_kg' => 80,
        'reps' => 5,
    ]);
});

test('guests cannot create, react to, or comment on recaps', function () {
    $recap = Recap::factory()->create([
        'workout_session_id' => $this->session->id,
    ]);

    $this->postJson(route('member.recaps.store'), [])->assertUnauthorized();
    $this->postJson(route('member.recaps.bravo', $recap))->assertUnauthorized();
    $this->postJson(route('member.recaps.comments.store', $recap))->assertUnauthorized();
});

test('members can share a recap with a photo and caption', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->user)->postJson(
        route('member.recaps.store'),
        [
            'workout_session_id' => $this->session->id,
            'caption' => 'Crushed leg day',
            'photo' => UploadedFile::fake()->image('gym.jpg', 800, 600),
        ],
    );

    $response->assertCreated()
        ->assertJsonPath('recap.caption', 'Crushed leg day')
        ->assertJsonPath('recap.summary.set_count', 1)
        ->assertJsonPath('recap.summary.exercise_count', 1)
        ->assertJsonPath('recap.summary.volume_kg', 400)
        ->assertJsonPath('recap.summary.exercises.0.weight_kg', 80)
        ->assertJsonPath('recap.summary.exercises.0.reps', 5)
        ->assertJsonPath('recap.bravos_count', 0)
        ->assertJsonPath('recap.can_delete', true);

    $recap = Recap::where('workout_session_id', $this->session->id)->sole();
    Storage::disk('public')->assertExists($recap->image_path);
    expect($response->json('recap.image_url'))->not->toBeNull();
});

test('a recap can be shared without a photo or caption', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('member.recaps.store'),
        ['workout_session_id' => $this->session->id],
    );

    $response->assertCreated()
        ->assertJsonPath('recap.caption', null)
        ->assertJsonPath('recap.image_url', null);
});

test('sharing marks the session as having a recap', function () {
    $this->actingAs($this->user)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
    ])->assertCreated();

    $this->actingAs($this->user)
        ->postJson(route('member.sessions.finish', $this->session))
        ->assertOk()
        ->assertJsonPath('session.has_recap', true);
});

test('members cannot share someone elses session', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
    ])->assertForbidden();
});

test('an empty session cannot be shared', function () {
    $this->session->sets()->delete();

    $this->actingAs($this->user)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
    ])->assertUnprocessable();
});

test('a session can only be shared once', function () {
    Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);

    $this->actingAs($this->user)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
    ])->assertUnprocessable();
});

test('non-image photos are rejected', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
        'photo' => UploadedFile::fake()->create('notes.pdf', 100),
    ])->assertUnprocessable();
});

test('overlong captions are rejected', function () {
    $this->actingAs($this->user)->postJson(route('member.recaps.store'), [
        'workout_session_id' => $this->session->id,
        'caption' => str_repeat('a', 281),
    ])->assertUnprocessable();
});

test('owners can edit their caption but others cannot', function () {
    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
        'caption' => 'old',
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('member.recaps.update', $recap), ['caption' => 'new'])
        ->assertOk()
        ->assertJsonPath('recap.caption', 'new');

    $this->actingAs(User::factory()->create())
        ->patchJson(route('member.recaps.update', $recap), ['caption' => 'hijack'])
        ->assertForbidden();
});

test('deleting a recap removes the photo, bravos and comments', function () {
    Storage::fake('public');
    Storage::disk('public')->put('recap-images/1/pic.jpg', 'x');

    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
        'image_path' => 'recap-images/1/pic.jpg',
    ]);
    RecapBravo::factory()->create(['recap_id' => $recap->id]);
    RecapComment::factory()->create(['recap_id' => $recap->id]);

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('member.recaps.destroy', $recap))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->deleteJson(route('member.recaps.destroy', $recap))
        ->assertOk();

    expect(Recap::find($recap->id))->toBeNull();
    expect(RecapBravo::count())->toBe(0);
    expect(RecapComment::count())->toBe(0);
    Storage::disk('public')->assertMissing('recap-images/1/pic.jpg');
});

test('bravos toggle on and off', function () {
    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);
    $fan = User::factory()->create();

    $this->actingAs($fan)
        ->postJson(route('member.recaps.bravo', $recap))
        ->assertOk()
        ->assertJsonPath('bravos_count', 1)
        ->assertJsonPath('bravoed', true);

    $this->actingAs($fan)
        ->postJson(route('member.recaps.bravo', $recap))
        ->assertOk()
        ->assertJsonPath('bravos_count', 0)
        ->assertJsonPath('bravoed', false);
});

test('comments can be added and paged', function () {
    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('member.recaps.comments.store', $recap), [
            'body' => 'Nice work',
        ])
        ->assertCreated()
        ->assertJsonPath('comment.body', 'Nice work')
        ->assertJsonPath('comments_count', 1);

    RecapComment::factory()->count(12)->create(['recap_id' => $recap->id]);

    $first = $this->actingAs($this->user)
        ->getJson(route('member.recaps.comments.index', $recap))
        ->assertOk();

    expect($first->json('comments'))->toHaveCount(10);
    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(
        route('member.recaps.comments.index', [
            'recap' => $recap,
            'before' => $cursor,
        ]),
    )->assertOk();

    expect($second->json('comments'))->toHaveCount(3);
    expect($second->json('next_cursor'))->toBeNull();
});

test('comment authors and recap owners can delete comments', function () {
    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);
    $author = User::factory()->create();
    $comment = RecapComment::factory()->create([
        'recap_id' => $recap->id,
        'user_id' => $author->id,
    ]);
    $other = RecapComment::factory()->create(['recap_id' => $recap->id]);

    // Unrelated member cannot delete.
    $this->actingAs(User::factory()->create())
        ->deleteJson(route('member.recaps.comments.destroy', [
            'recap' => $recap,
            'comment' => $comment,
        ]))
        ->assertForbidden();

    // Recap owner can moderate their thread.
    $this->actingAs($this->user)
        ->deleteJson(route('member.recaps.comments.destroy', [
            'recap' => $recap,
            'comment' => $other,
        ]))
        ->assertOk();

    // Author can delete their own.
    $this->actingAs($author)
        ->deleteJson(route('member.recaps.comments.destroy', [
            'recap' => $recap,
            'comment' => $comment,
        ]))
        ->assertOk()
        ->assertJsonPath('comments_count', 0);
});

test('comments bound to a different recap cannot be deleted through this post', function () {
    $recap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);
    $otherSession = WorkoutSession::factory()->create(['user_id' => $this->user->id]);
    $otherRecap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $otherSession->id,
    ]);
    $comment = RecapComment::factory()->create([
        'recap_id' => $otherRecap->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson(route('member.recaps.comments.destroy', [
            'recap' => $recap,
            'comment' => $comment,
        ]))
        ->assertNotFound();
});

test('home shows the community feed with viewer flags', function () {
    $ownRecap = Recap::factory()->create([
        'user_id' => $this->user->id,
        'workout_session_id' => $this->session->id,
    ]);
    $otherSession = WorkoutSession::factory()
        ->for(User::factory()->create())
        ->create();
    WorkoutSet::factory()->create([
        'workout_session_id' => $otherSession->id,
        'exercise_id' => 'squat',
        'weight_kg' => 60,
        'reps' => 10,
    ]);
    $otherRecap = Recap::factory()->create([
        'workout_session_id' => $otherSession->id,
    ]);
    RecapBravo::factory()->create([
        'recap_id' => $otherRecap->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/home')
            ->has('recaps', 2)
            ->where('recaps.0.id', $otherRecap->id)
            ->where('recaps.0.bravoed', true)
            ->where('recaps.0.bravos_count', 1)
            ->where('recaps.0.can_delete', false)
            ->where('recaps.1.id', $ownRecap->id)
            ->where('recaps.1.can_delete', true)
            ->where('recaps.1.summary.title', null)
            ->where('recaps.1.summary.volume_kg', 400));
});

test('the feed pages older recaps via the before cursor', function () {
    Recap::factory()->count(12)->create();

    $first = $this->actingAs($this->user)
        ->getJson(route('member.recaps.index'))
        ->assertOk();

    expect($first->json('recaps'))->toHaveCount(10);
    $cursor = $first->json('next_cursor');

    $second = $this->getJson(
        route('member.recaps.index', ['before' => $cursor]),
    )->assertOk();

    expect($second->json('recaps'))->toHaveCount(2);
    expect($second->json('next_cursor'))->toBeNull();
});
