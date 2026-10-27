<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\User;
use App\Services\Programs\ProgramAccessGranter;
use Inertia\Testing\AssertableInertia as Assert;

function memberProgram(array $attributes = []): ExerciseProgram
{
    Exercise::factory()->create(['slug' => 'squat', 'name' => 'Squat']);
    Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push Up']);

    $program = ExerciseProgram::factory()->create($attributes);

    $legs = $program->workouts()->create(['title' => 'Legs', 'position' => 0]);
    $legs->items()->create(['exercise_id' => 'squat', 'position' => 0]);

    $push = $program->workouts()->create(['title' => 'Push', 'position' => 1]);
    $push->items()->create(['exercise_id' => 'push-up', 'position' => 0]);

    return $program;
}

test('guests are redirected to login', function () {
    $program = memberProgram();

    $this->get(route('member.programs.show', $program))
        ->assertRedirect(route('login'));
});

test('a claiming member sees the program workouts from their copy', function () {
    $program = memberProgram(['name' => 'Starter']);
    $user = proUser();
    app(ProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->get(route('member.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/program-show')
            ->where('program.name', 'Starter')
            ->has('workouts', 2)
            ->where('workouts.0.workout_title', 'Legs')
            ->where('workouts.1.workout_title', 'Push')
            ->has('sessions')
            ->has('exerciseSummaries'));
});

test('members without a claim cannot open the program page', function () {
    $program = memberProgram();

    $this->actingAs(User::factory()->create())
        ->get(route('member.programs.show', $program))
        ->assertNotFound();
});

test('admins can open the program page without a claim', function () {
    $program = memberProgram();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('member.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/program-show'));
});

test('opening the workout player claims the program and starts today\'s session', function () {
    $program = memberProgram();
    $user = proUser();
    $workout = $program->workouts()->where('title', 'Legs')->sole();

    $this->actingAs($user)
        ->get(route('member.program-workouts.play', $workout))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/workout-player')
            ->where('workout.id', $workout->id)
            ->has('workout.exercises', 1)
            ->has('workout.exercises.0.gif_url')
            ->has('session')
            ->has('backUrl'));

    $playlist = $user->exercisePlaylists()->where('exercise_program_workout_id', $workout->id)->sole();
    expect($user->workoutSessions()->sole()->exercise_playlist_id)->toBe($playlist->id);
});

test('the workout player resumes today\'s session instead of duplicating it', function () {
    $program = memberProgram();
    $user = proUser();
    $workout = $program->workouts()->where('title', 'Legs')->sole();

    $this->actingAs($user)->get(route('member.program-workouts.play', $workout));
    $this->actingAs($user)->get(route('member.program-workouts.play', $workout));

    expect($user->workoutSessions()->count())->toBe(1);
});

test('non-pro members without a claim cannot open the workout player', function () {
    $program = memberProgram();
    $workout = $program->workouts()->first();

    $this->actingAs(User::factory()->create())
        ->get(route('member.program-workouts.play', $workout))
        ->assertForbidden();
});
