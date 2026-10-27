<?php

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\ExerciseCatalog;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Six catalog exercises for every body part the daily generator rotates over.
 */
function seedFocusExerciseCatalog(): void
{
    foreach (ExerciseCatalog::FOCUS_BODY_PARTS as $part) {
        Exercise::factory()->forBodyPart($part)->count(6)->create();
    }
}

test('guests are redirected to login', function () {
    $this->get(route('member.today'))->assertRedirect(route('login'));
});

test('today page provides a generated playlist for the day', function () {
    seedFocusExerciseCatalog();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/today')
            ->has('playlist.exercise_ids', 6)
            ->has('muscleFocus.muscle')
            ->has('playlists')
            ->has('sessions')
            ->has('exerciseSummaries'));

    expect(ExercisePlaylist::where('user_id', $user->id)
        ->whereDate('generated_for', today())
        ->count())->toBe(1);
});

test('the generated playlist is reused on repeat visits the same day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('member.today'))->assertOk();
    $this->get(route('member.today'))->assertOk();

    expect(ExercisePlaylist::where('user_id', $user->id)
        ->whereNotNull('generated_for')
        ->count())->toBe(1);
});

test('generation targets a muscle not trained recently', function () {
    seedFocusExerciseCatalog();
    $user = User::factory()->create();
    $chestExercise = Exercise::where('body_part', 'chest')->firstOrFail();

    // Chest was trained the last two days; untrained muscles should win.
    foreach ([1, 2] as $daysAgo) {
        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'date' => today()->subDays($daysAgo),
        ]);
        $session->sets()->create([
            'exercise_id' => $chestExercise->slug,
            'set_index' => 0,
            'weight_kg' => 60,
            'reps' => 10,
        ]);
    }

    $this->actingAs($user)
        ->get(route('member.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('muscleFocus.muscle', 'back')
            ->has('playlist.exercise_ids', 6));

    $playlist = ExercisePlaylist::where('user_id', $user->id)
        ->whereDate('generated_for', today())
        ->firstOrFail();

    $bodyParts = Exercise::whereIn('slug', $playlist->items->pluck('exercise_id'))
        ->pluck('body_part')
        ->unique()
        ->values()
        ->all();

    expect($bodyParts)->toBe(['back']);
});

test('generated playlists are hidden from the exercise page list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->get(route('member.today'))->assertOk();

    $this->get(route('member.exercise'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('playlists', 0));
});
