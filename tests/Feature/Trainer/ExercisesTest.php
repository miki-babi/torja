<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\User;
use App\Support\ExerciseCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function trainer(): User
{
    $user = User::factory()->trainer()->create();
    $user->ensureTrainer();

    return $user;
}

function customExerciseFor(User $user, array $overrides = []): Exercise
{
    return $user->customExercises()->create([
        'slug' => 'custom-'.fake()->unique()->lexify('??????'),
        'name' => 'Custom lift',
        'body_part' => 'chest',
        'equipment' => 'dumbbell',
        'target' => 'pectorals',
        'level' => 'beginner',
        'instructions' => '',
        'instruction_steps' => [],
        'image_path' => '',
        'gif_path' => '',
        'source_id' => 'custom',
        'category' => 'custom',
        'muscle_group' => 'custom',
        'secondary_muscles' => [],
        'media_id' => '',
        'exercise_type' => 'custom',
        'location' => 'any',
        'compound' => false,
        'isolation' => false,
        'full_body' => false,
        'women_home_friendly' => true,
        'goal_fat_loss' => false,
        'goal_muscle_gain' => false,
        'review_needed' => false,
        'review_reasons' => [],
        ...$overrides,
    ]);
}

test('a trainer creates a custom exercise with a minted slug', function () {
    $user = trainer();

    $this->actingAs($user)
        ->post(route('trainer.exercises.store'), [
            'name' => 'Bench press', // same name as the catalog entry — allowed
            'body_part' => 'chest',
            'equipment' => 'barbell',
            'target' => 'pectorals',
            'level' => 'intermediate',
            'instructions' => "Lie down.\nPush up.",
        ])
        ->assertRedirect(route('trainer.exercises.index'));

    $exercise = Exercise::where('user_id', $user->id)->sole();

    expect($exercise->slug)->toStartWith('custom-bench-press-')
        ->and($exercise->isCustom())->toBeTrue()
        ->and($exercise->instruction_steps)->toBe(['Lie down.', 'Push up.']);
});

test('media uploads store image and video', function () {
    Storage::fake('public');
    $user = trainer();

    $this->actingAs($user)
        ->post(route('trainer.exercises.store'), [
            'name' => 'Band row',
            'body_part' => 'back',
            'equipment' => 'band',
            'target' => 'lats',
            'level' => 'beginner',
            'image' => UploadedFile::fake()->image('row.jpg'),
            'video' => UploadedFile::fake()->create('row.mp4', 500, 'video/mp4'),
        ])
        ->assertRedirect();

    $exercise = Exercise::where('user_id', $user->id)->sole();

    Storage::disk('public')->assertExists($exercise->image_path);
    Storage::disk('public')->assertExists($exercise->gif_path);
    expect($exercise->isVideoDemo())->toBeTrue();
});

test('the exercise search merges own customs but not other trainers', function () {
    $user = trainer();
    $other = trainer();
    customExerciseFor($user, ['name' => 'Zebra squat']);
    customExerciseFor($other, ['name' => 'Zebra deadlift']);

    $results = $this->actingAs($user)
        ->getJson(route('member.exercises.search', ['q' => 'Zebra']))
        ->assertOk()
        ->json('exercises');

    expect(collect($results)->pluck('name')->all())->toBe(['Zebra squat'])
        ->and($results[0]['is_custom'])->toBeTrue();
});

test('programs accept own custom slugs but reject other trainers', function () {
    $user = trainer();
    $other = trainer();
    $mine = customExerciseFor($user);
    $theirs = customExerciseFor($other);

    $this->actingAs($user)
        ->post(route('trainer.programs.store'), [
            'name' => 'My program',
            'price_etb' => 0,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => $mine->slug]]],
            ],
        ])
        ->assertRedirect();

    expect(ExerciseProgram::where('user_id', $user->id)->sole()->workouts->first()->items->first()->exercise_id)
        ->toBe($mine->slug);

    $this->actingAs($user)
        ->post(route('trainer.programs.store'), [
            'name' => 'Sneaky',
            'price_etb' => 0,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => $theirs->slug]]],
            ],
        ])
        ->assertSessionHasErrors('workouts.0.exercises.0.slug');
});

test('custom exercises stay out of the platform catalog surfaces', function () {
    $user = trainer();
    customExerciseFor($user, ['name' => 'Zebra fly', 'body_part' => 'chest']);

    // Catalog maps + "Full body" count ignore customs.
    expect(ExerciseCatalog::idsFor('chest'))
        ->not->toContain(Exercise::where('user_id', $user->id)->sole()->slug);

    // Member search never sees them.
    $member = User::factory()->create();
    $results = $this->actingAs($member)
        ->getJson(route('member.exercises.search', ['q' => 'Zebra']))
        ->json('exercises');
    expect($results)->toBe([]);

    // Staff library lists platform only.
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff)
        ->get(route('admin.exercises.index', ['search' => 'Zebra']))
        ->assertInertia(fn (Assert $page) => $page->where('exercises.total', 0));
});

test('a trainer cannot edit or delete another trainers exercise', function () {
    $user = trainer();
    $other = trainer();
    $theirs = customExerciseFor($other);

    $this->actingAs($user)
        ->get(route('trainer.exercises.edit', $theirs->slug))
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('trainer.exercises.destroy', $theirs->slug))
        ->assertNotFound();
});

test('deletion is blocked while a program references the exercise', function () {
    $user = trainer();
    $exercise = customExerciseFor($user);

    $program = ExerciseProgram::factory()->for($user)->create();
    $workout = $program->workouts()->create(['title' => 'Day 1', 'position' => 0]);
    $workout->items()->create(['exercise_id' => $exercise->slug, 'position' => 0]);

    $this->actingAs($user)
        ->delete(route('trainer.exercises.destroy', $exercise->slug))
        ->assertRedirect();

    expect($exercise->fresh())->not->toBeNull();

    $workout->items()->delete();

    $this->actingAs($user)
        ->delete(route('trainer.exercises.destroy', $exercise->slug))
        ->assertRedirect(route('trainer.exercises.index'));

    expect(Exercise::find($exercise->id))->toBeNull();
});
