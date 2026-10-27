<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function programAdmin(): User
{
    return User::factory()->admin()->create();
}

test('non-admin users cannot manage programs', function () {
    $program = ExerciseProgram::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.programs.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.programs.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.programs.update', $program), [])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.programs.destroy', $program))->assertForbidden();
    $this->actingAs($user)->post(route('admin.programs.share', $program))->assertForbidden();
    $this->actingAs($user)->post(route('admin.programs.publish', $program))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.programs.unpublish', $program))->assertForbidden();
});

test('admins can list programs with counts', function () {
    ExerciseProgram::factory()->count(2)->create();

    $this->actingAs(programAdmin())
        ->get(route('admin.programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/index')
            ->has('programs.data', 2));
});

test('admins can create a program with nested workouts', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    Exercise::factory()->create(['slug' => 'push-up']);

    $this->actingAs(programAdmin())
        ->post(route('admin.programs.store'), [
            'name' => 'Full Body',
            'description' => 'Two day split',
            'price_etb' => 250,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [
                    ['slug' => 'squat', 'sets' => 4, 'reps' => 8],
                    ['slug' => 'push-up', 'sets' => null, 'reps' => null],
                ]],
                ['title' => 'Day 2', 'exercises' => [['slug' => 'squat']]],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $program = ExerciseProgram::sole();
    $dayOne = $program->workouts()->where('title', 'Day 1')->sole();
    $squat = $dayOne->items()->where('exercise_id', 'squat')->sole();

    expect($program->name)->toBe('Full Body')
        ->and($program->price_etb)->toBe(250)
        ->and($program->workouts()->count())->toBe(2)
        ->and($dayOne->items()->count())->toBe(2)
        ->and($squat->sets)->toBe(4)
        ->and($squat->reps)->toBe(8)
        ->and($dayOne->items()->where('exercise_id', 'push-up')->sole()->sets)->toBeNull();
});

test('creating a program rejects out-of-range set and rep targets', function () {
    Exercise::factory()->create(['slug' => 'squat']);

    $this->actingAs(programAdmin())
        ->post(route('admin.programs.store'), [
            'name' => 'Bad Targets',
            'price_etb' => 0,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => 'squat', 'sets' => 0, 'reps' => 500]]],
            ],
        ])
        ->assertSessionHasErrors(['workouts.0.exercises.0.sets', 'workouts.0.exercises.0.reps']);
});

test('creating a program rejects unknown exercise slugs', function () {
    $this->actingAs(programAdmin())
        ->post(route('admin.programs.store'), [
            'name' => 'Bad Program',
            'price_etb' => 0,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => 'not-an-exercise']]],
            ],
        ])
        ->assertSessionHasErrors('workouts.0.exercises.0.slug');
});

test('admins can update a program and sync its workout tree', function () {
    Exercise::factory()->create(['slug' => 'squat']);
    Exercise::factory()->create(['slug' => 'lunge']);

    $program = ExerciseProgram::factory()->create();
    $workout = $program->workouts()->create(['title' => 'Old Day', 'position' => 0]);
    $workout->items()->create(['exercise_id' => 'squat', 'position' => 0]);
    $removed = $program->workouts()->create(['title' => 'Removed', 'position' => 1]);

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => 'Renamed',
            'description' => null,
            'price_etb' => 0,
            'workouts' => [
                ['id' => $workout->id, 'title' => 'New Day', 'exercises' => [
                    ['slug' => 'lunge', 'sets' => 3, 'reps' => 12],
                    ['slug' => 'squat'],
                ]],
            ],
        ])
        ->assertRedirect(route('admin.programs.edit', $program))
        ->assertSessionHasNoErrors();

    $program->refresh();
    expect($program->name)->toBe('Renamed')
        ->and($program->workouts()->count())->toBe(1)
        ->and($program->workouts()->where('id', $removed->id)->exists())->toBeFalse()
        ->and($workout->refresh()->title)->toBe('New Day')
        ->and($workout->items()->pluck('exercise_id')->all())->toBe(['lunge', 'squat'])
        ->and($workout->items()->where('exercise_id', 'lunge')->sole()->reps)->toBe(12);
});

test('admins can set a cover image and sales highlights', function () {
    Storage::fake('public');
    Exercise::factory()->create(['slug' => 'squat']);

    $program = ExerciseProgram::factory()->create();

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => 'With Cover',
            'price_etb' => 100,
            'image' => UploadedFile::fake()->image('hero.jpg'),
            'highlights' => ['Lose weight fast', 'Six guided days'],
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => 'squat']]],
            ],
        ])
        ->assertRedirect(route('admin.programs.edit', $program))
        ->assertSessionHasNoErrors();

    $program->refresh();
    expect($program->image_path)->toStartWith('programs/')
        ->and($program->highlights)->toBe(['Lose weight fast', 'Six guided days'])
        ->and($program->image_url)->toContain('programs/');

    Storage::disk('public')->assertExists($program->image_path);
});

test('a new cover image replaces the old file', function () {
    Storage::fake('public');
    Exercise::factory()->create(['slug' => 'squat']);

    $program = ExerciseProgram::factory()->create([
        'image_path' => 'programs/old.jpg',
    ]);
    Storage::disk('public')->put('programs/old.jpg', 'old');

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => $program->name,
            'price_etb' => 0,
            'image' => UploadedFile::fake()->image('new.jpg'),
            'workouts' => [],
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('programs/old.jpg');
    expect($program->refresh()->image_path)->not->toBe('programs/old.jpg');
});

test('keeping the program without a new image preserves image_path', function () {
    Storage::fake('public');
    Exercise::factory()->create(['slug' => 'squat']);

    $program = ExerciseProgram::factory()->create([
        'image_path' => 'programs/keep.jpg',
    ]);

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => $program->name,
            'price_etb' => 0,
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => 'squat']]],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->image_path)->toBe('programs/keep.jpg');
});

test('admins can save Amharic program content', function () {
    Exercise::factory()->create(['slug' => 'squat']);

    $program = ExerciseProgram::factory()->create();

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => 'Weight Loss',
            'name_am' => 'የክብደት መቀነስ',
            'description' => 'For men',
            'description_am' => 'ለወንዶች',
            'price_etb' => 0,
            'highlights' => ['Lose weight'],
            'highlights_am' => ['ክብደት ይቀንሱ'],
            'workouts' => [
                ['title' => 'Day 1', 'exercises' => [['slug' => 'squat']]],
            ],
        ])
        ->assertRedirect(route('admin.programs.edit', $program))
        ->assertSessionHasNoErrors();

    $program->refresh();
    expect($program->name_am)->toBe('የክብደት መቀነስ')
        ->and($program->description_am)->toBe('ለወንዶች')
        ->and($program->highlights_am)->toBe(['ክብደት ይቀንሱ']);
});

test('admins can delete a program', function () {
    $program = ExerciseProgram::factory()->create();

    $this->actingAs(programAdmin())
        ->delete(route('admin.programs.destroy', $program))
        ->assertRedirect(route('admin.programs.index'));

    expect(ExerciseProgram::count())->toBe(0);
});

test('share mints a stable token and unshare clears it', function () {
    $program = ExerciseProgram::factory()->unshared()->create();

    $this->actingAs(programAdmin());

    $url = $this->postJson(route('admin.programs.share', $program))
        ->assertOk()
        ->json('share_url');

    $this->get($url)->assertRedirectContains('shared/programs/');
    expect($program->refresh()->share_token)->not->toBeNull();

    $this->postJson(route('admin.programs.share', $program))
        ->assertOk()
        ->assertJsonPath('share_url', $url);

    $this->deleteJson(route('admin.programs.unshare', $program))
        ->assertOk()
        ->assertJsonPath('share_url', null);

    expect($program->refresh()->share_token)->toBeNull();
});

test('the edit page includes the workout tree and exercise summaries', function () {
    Exercise::factory()->create(['slug' => 'squat', 'name' => 'Squat']);

    $program = ExerciseProgram::factory()->create();
    $workout = $program->workouts()->create(['title' => 'Legs', 'position' => 0]);
    $workout->items()->create(['exercise_id' => 'squat', 'position' => 0]);

    $this->actingAs(programAdmin())
        ->get(route('admin.programs.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/programs/edit')
            ->where('program.id', $program->id)
            ->has('program.name_am')
            ->has('program.description_am')
            ->has('program.image_url')
            ->has('program.highlights')
            ->has('program.highlights_am')
            ->has('program.workouts', 1)
            ->where('program.workouts.0.exercises.0.slug', 'squat')
            ->where('program.workouts.0.exercises.0.sets', null)
            ->has('exerciseSummaries.squat.name')
            ->has('bodyParts')
            ->has('equipment')
            ->has('levels'));
});

test('admins can publish and unpublish a program', function () {
    $program = ExerciseProgram::factory()->unshared()->create();

    $this->actingAs(programAdmin())
        ->postJson(route('admin.programs.publish', $program))
        ->assertOk()
        ->assertJsonPath('published', true);

    expect($program->refresh()->isPublished())->toBeTrue()
        ->and($program->share_token)->not->toBeNull();

    $this->actingAs(programAdmin())
        ->deleteJson(route('admin.programs.unpublish', $program))
        ->assertOk()
        ->assertJsonPath('published', false);

    expect($program->refresh()->isPublished())->toBeFalse()
        ->and($program->share_token)->not->toBeNull();
});

test('publishing reuses an existing share token', function () {
    $program = ExerciseProgram::factory()->create();
    $token = $program->share_token;

    $this->actingAs(programAdmin())
        ->postJson(route('admin.programs.publish', $program))
        ->assertOk();

    expect($program->refresh()->share_token)->toBe($token);
});

test('the update form can publish and unpublish a program', function () {
    $program = ExerciseProgram::factory()->unshared()->create();

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => 'Listed Program',
            'price_etb' => 0,
            'is_published' => true,
        ])
        ->assertRedirect();

    expect($program->refresh()->isPublished())->toBeTrue()
        ->and($program->share_token)->not->toBeNull();

    $this->actingAs(programAdmin())
        ->put(route('admin.programs.update', $program), [
            'name' => 'Listed Program',
            'price_etb' => 0,
            'is_published' => false,
        ])
        ->assertRedirect();

    expect($program->refresh()->isPublished())->toBeFalse();
});
