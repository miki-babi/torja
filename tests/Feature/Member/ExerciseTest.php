<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\User;
use App\Services\Programs\ProgramAccessGranter;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('member.exercise'))->assertRedirect(route('login'));
});

test('members can view the exercise page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.exercise'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/exercise')
            ->has('playlists')
            ->has('sessions')
            ->has('exerciseSummaries')
            ->has('bodyParts', 10)
            ->has('equipment')
            ->has('levels')
            ->has('initialExercises'));
});

test('playlists claimed from a program expose their program and workout title', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    $program = ExerciseProgram::factory()->create(['name' => 'Starter']);
    $workout = $program->workouts()->create(['title' => 'Legs', 'position' => 0]);
    $workout->items()->create(['exercise_id' => 'squat', 'position' => 0]);

    $user = proUser();
    app(ProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->get(route('member.exercise'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlists.0.program_id', $program->id)
            ->where('playlists.0.program_name', 'Starter')
            ->where('playlists.0.workout_title', 'Legs'));
});

test('members can view an exercise detail page', function () {
    $exercise = Exercise::factory()->create(['slug' => 'barbell-bench-press']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.exercises.show', 'barbell-bench-press'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/exercise-show')
            ->where('exercise.slug', 'barbell-bench-press')
            ->where('exercise.name', $exercise->name)
            ->has('playlists')
            ->has('sessions')
            ->has('similarMuscle')
            ->has('similarEquipment'));
});

test('exercise detail suggests exercises with the same target and equipment', function () {
    Exercise::factory()->create(['slug' => 'subject', 'target' => 'abs', 'equipment' => 'body weight']);
    Exercise::factory()->create(['slug' => 'same-muscle', 'target' => 'abs', 'equipment' => 'band']);
    Exercise::factory()->create(['slug' => 'same-equipment', 'target' => 'pectorals', 'equipment' => 'body weight']);
    Exercise::factory()->create(['slug' => 'unrelated', 'target' => 'pectorals', 'equipment' => 'barbell']);

    $this->actingAs(User::factory()->create())
        ->get(route('member.exercises.show', 'subject'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/exercise-show')
            ->has('similarMuscle', 1)
            ->where('similarMuscle.0.slug', 'same-muscle')
            ->has('similarEquipment', 1)
            ->where('similarEquipment.0.slug', 'same-equipment'));
});

test('legacy catalog ids redirect to the canonical slug', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);

    $this->actingAs(User::factory()->create())
        ->get(route('member.exercises.show', 'bench-press'))
        ->assertRedirect(route('member.exercises.show', 'barbell-bench-press'));
});

test('unknown exercise ids return 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.exercises.show', 'not-a-move'))
        ->assertNotFound();
});

test('exercise search matches by name', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press', 'name' => 'barbell bench press']);
    Exercise::factory()->create(['slug' => 'push-up', 'name' => 'push-up']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('member.exercises.search', ['q' => 'bench']))
        ->assertOk()
        ->assertJsonCount(1, 'exercises')
        ->assertJsonPath('exercises.0.slug', 'barbell-bench-press')
        ->assertJsonPath('total', 1);
});

test('exercise search filters by body part, equipment and level', function () {
    Exercise::factory()->create([
        'slug' => 'barbell-bench-press',
        'body_part' => 'chest',
        'equipment' => 'barbell',
        'level' => 'intermediate',
    ]);
    Exercise::factory()->create([
        'slug' => 'push-up',
        'body_part' => 'chest',
        'equipment' => 'body weight',
        'level' => 'beginner',
    ]);
    Exercise::factory()->forBodyPart('back')->create(['slug' => 'pull-up']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('member.exercises.search', ['body_part' => 'chest']))
        ->assertJsonCount(2, 'exercises');

    $this->actingAs($user)
        ->getJson(route('member.exercises.search', [
            'body_part' => 'chest',
            'equipment' => 'barbell',
            'level' => 'intermediate',
        ]))
        ->assertJsonCount(1, 'exercises')
        ->assertJsonPath('exercises.0.slug', 'barbell-bench-press');
});

test('exercise search paginates with offset', function () {
    Exercise::factory()->forBodyPart('chest')->count(5)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('member.exercises.search', ['limit' => 3]))
        ->assertJsonCount(3, 'exercises')
        ->assertJsonPath('total', 5);

    $this->actingAs($user)
        ->getJson(route('member.exercises.search', ['limit' => 3, 'offset' => 3]))
        ->assertJsonCount(2, 'exercises');
});

test('exercise search requires auth', function () {
    $this->getJson(route('member.exercises.search'))->assertUnauthorized();
});
