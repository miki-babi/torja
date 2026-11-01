<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\ExerciseCatalog;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Six catalog exercises for every body part, split across equipment.
 */
function seedBuilderExerciseCatalog(string $equipment = 'body weight'): void
{
    foreach (ExerciseCatalog::FOCUS_BODY_PARTS as $part) {
        Exercise::factory()->forBodyPart($part)->count(6)->create(['equipment' => $equipment]);
    }
}

test('guests are redirected to login', function () {
    $this->get(route('member.today'))->assertRedirect(route('login'));
});

test('today page provides builder data without generating a workout', function () {
    seedBuilderExerciseCatalog();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/today')
            ->has('bodyParts')
            ->has('equipment')
            ->has('playlists')
            ->has('sessions')
            ->has('exerciseSummaries')
            ->missing('playlist')
            ->missing('muscleFocus'));

    expect(ExercisePlaylist::where('user_id', $user->id)->count())->toBe(0);
});

test('builder generates a preview playlist filtered by muscle and equipment', function () {
    Exercise::factory()->forBodyPart('chest')->count(8)->create(['equipment' => 'barbell']);
    Exercise::factory()->forBodyPart('chest')->count(8)->create(['equipment' => 'band']);
    Exercise::factory()->forBodyPart('back')->count(8)->create(['equipment' => 'barbell']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['chest'],
        'equipment' => ['barbell'],
    ]);

    $response->assertCreated()->assertJsonStructure(['playlist', 'exerciseSummaries']);

    $playlist = ExercisePlaylist::where('user_id', $user->id)
        ->whereDate('generated_for', today())
        ->sole();

    $exercises = Exercise::whereIn('slug', $playlist->items->pluck('exercise_id'));

    expect($playlist->items)->toHaveCount(6)
        ->and($exercises->pluck('body_part')->unique()->values()->all())->toBe(['chest'])
        ->and($exercises->pluck('equipment')->unique()->values()->all())->toBe(['barbell'])
        ->and($response->json('playlist.id'))->toBe($playlist->id);

    // Building only previews the workout — no session until the member starts.
    expect(WorkoutSession::where('user_id', $user->id)->count())->toBe(0);
});

test('starting a built workout opens a session on the playlist', function () {
    Exercise::factory()->forBodyPart('chest')->count(6)->create();

    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['chest'],
    ])->assertCreated();

    $playlist = ExercisePlaylist::where('user_id', $user->id)->sole();

    $response = $this->actingAs($user)->postJson(route('member.sessions.store'), [
        'exercise_playlist_id' => $playlist->id,
    ]);

    $response->assertCreated();

    $session = WorkoutSession::where('user_id', $user->id)->sole();

    expect($session->exercise_playlist_id)->toBe($playlist->id)
        ->and($response->json('session.id'))->toBe($session->id);
});

test('builder expands grouped muscles and draws from every pick', function () {
    Exercise::factory()->forBodyPart('upper arms')->count(8)->create();
    Exercise::factory()->forBodyPart('lower arms')->count(8)->create();
    Exercise::factory()->forBodyPart('chest')->count(8)->create();

    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['arms', 'chest'],
    ])->assertCreated();

    $playlist = ExercisePlaylist::where('user_id', $user->id)->sole();

    $parts = Exercise::whereIn('slug', $playlist->items->pluck('exercise_id'))
        ->pluck('body_part')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($parts)->toEqualCanonicalizing(['chest', 'upper arms', 'lower arms']);
});

test('builder with no picks draws from the full body', function () {
    seedBuilderExerciseCatalog();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => [],
        'equipment' => [],
    ])->assertCreated();

    $playlist = ExercisePlaylist::where('user_id', $user->id)->sole();

    expect($playlist->items)->toHaveCount(6)
        ->and($playlist->name)->toBe('Full body workout');
});

test('builder rejects picks with no matching exercises', function () {
    Exercise::factory()->forBodyPart('chest')->count(6)->create(['equipment' => 'barbell']);
    Exercise::factory()->forBodyPart('waist')->count(6)->create(['equipment' => 'body weight']);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['waist'],
        'equipment' => ['barbell'],
    ])->assertUnprocessable();

    expect(ExercisePlaylist::where('user_id', $user->id)->count())->toBe(0)
        ->and(WorkoutSession::where('user_id', $user->id)->count())->toBe(0);
});

test('builder rejects unknown muscles and equipment', function () {
    $user = User::factory()->create();
    Exercise::factory()->create(['equipment' => 'barbell']);

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['wings'],
        'equipment' => ['barbell'],
    ])->assertUnprocessable();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['chest'],
        'equipment' => ['hopes and dreams'],
    ])->assertUnprocessable();
});

test('active session prop exposes today\'s latest session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('member.today'))
        ->assertInertia(fn ($page) => $page->where('activeSession', null));

    $session = WorkoutSession::factory()->create(['user_id' => $user->id, 'date' => today()]);

    $this->actingAs($user)->get(route('member.today'))
        ->assertInertia(fn ($page) => $page->where('activeSession.id', $session->id));
});

test('starting a new session finishes the previous one', function () {
    $user = User::factory()->create();

    $first = WorkoutSession::factory()->create(['user_id' => $user->id, 'date' => today()]);
    $second = WorkoutSession::factory()->create(['user_id' => $user->id, 'date' => today()]);

    expect($first->fresh()->finished_at)->not->toBeNull()
        ->and($second->fresh()->finished_at)->toBeNull();
});

test('finishing a session clears the active session prop', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->create(['user_id' => $user->id, 'date' => today()]);

    $this->actingAs($user)->postJson(route('member.sessions.finish', $session))
        ->assertOk();

    expect($session->fresh()->finished_at)->not->toBeNull();

    $this->actingAs($user)->get(route('member.today'))
        ->assertInertia(fn ($page) => $page->where('activeSession', null));
});

test('generated playlists are hidden from the exercise page list', function () {
    $user = User::factory()->create();
    seedBuilderExerciseCatalog();

    $this->actingAs($user)->postJson(route('member.workouts.build'), [
        'muscles' => ['chest'],
    ])->assertCreated();

    $this->get(route('member.exercise'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('playlists', 0));
});
