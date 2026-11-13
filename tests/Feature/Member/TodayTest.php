<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Programs\ProgramAccessGranter;
use Inertia\Testing\AssertableInertia as Assert;

function coachedProgram(User $member): ExerciseProgram
{
    Exercise::factory()->create(['slug' => 'squat', 'name' => 'Squat']);

    $coach = $member->activeCoach();
    $program = ExerciseProgram::factory()->create([
        'user_id' => $coach->user_id,
        'name' => 'Coach Split',
    ]);
    $program->workouts()->create(['title' => 'Legs', 'position' => 0])
        ->items()->create(['exercise_id' => 'squat', 'position' => 0]);
    $program->workouts()->create(['title' => 'Push', 'position' => 1])
        ->items()->create(['exercise_id' => 'squat', 'position' => 0]);

    app(ProgramAccessGranter::class)->grant($member, $program);

    return $program;
}

test('today shows the coach program and the first workout as next', function () {
    $member = User::factory()->create();
    $this->actingAs($member);
    $program = coachedProgram($member);

    $this->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/today')
            ->where('program.id', $program->id)
            ->where('program.name', 'Coach Split')
            ->has('workouts', 2)
            ->where('workouts.0.title', 'Legs')
            ->where('workouts.0.done', false)
            ->where('nextWorkoutId', $program->workouts()->where('position', 0)->value('id'))
            ->where('nextWorkoutUrl', route(
                'member.program-workouts.play',
                $program->workouts()->where('position', 0)->sole(),
            )));
});

test('today marks this weeks finished workouts as done and picks the next one', function () {
    $member = User::factory()->create();
    $this->actingAs($member);
    $program = coachedProgram($member);

    $playlist = $member->exercisePlaylists()
        ->where('exercise_program_workout_id', $program->workouts()->where('position', 0)->value('id'))
        ->sole();
    $member->workoutSessions()->create([
        'exercise_playlist_id' => $playlist->id,
        'date' => now()->toDateString(),
        'finished_at' => now(),
    ]);

    $this->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workouts.0.done', true)
            ->where('workouts.1.done', false)
            ->where('nextWorkoutId', $program->workouts()->where('position', 1)->value('id')));
});

test('today shows no program for a coached member without assignments', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/today')
            ->where('program', null)
            ->where('nextWorkoutUrl', null));
});

test('a program from another trainer is not picked up as the coach program', function () {
    $member = User::factory()->create();
    $this->actingAs($member);

    $outsider = Trainer::factory()->create();
    $program = ExerciseProgram::factory()->create(['user_id' => $outsider->user_id]);
    app(ProgramAccessGranter::class)->grant($member, $program);

    $this->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('program', null));
});
