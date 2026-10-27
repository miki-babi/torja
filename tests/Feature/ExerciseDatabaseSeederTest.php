<?php

use App\Models\Exercise;
use Database\Seeders\ExerciseDatabaseSeeder;

beforeEach(fn () => $this->seed(ExerciseDatabaseSeeder::class));

test('seeds every exercise with a unique slug', function () {
    expect(Exercise::count())->toBe(1324)
        ->and(Exercise::distinct('slug')->count())->toBe(1324);
});

test('maps dataset fields onto the exercise', function () {
    $exercise = Exercise::where('slug', 'barbell-bench-press')->firstOrFail();

    expect($exercise->source_id)->toBe('0025')
        ->and($exercise->name)->toBe('barbell bench press')
        ->and($exercise->body_part)->toBe('chest')
        ->and($exercise->equipment)->toBe('barbell')
        ->and($exercise->target)->toBe('pectorals')
        ->and($exercise->image_path)->toBe('exercises/images/0025-EIeI8Vf.jpg')
        ->and($exercise->gif_path)->toBe('exercises/videos/0025-EIeI8Vf.gif')
        ->and($exercise->instruction_steps)->not->toBeEmpty()
        ->and($exercise->secondary_muscles)->toBeArray()
        ->and($exercise->name_am)->not->toBeNull();
});

test('duplicate names get suffixed slugs deterministically', function () {
    expect(Exercise::where('slug', 'barbell-seated-calf-raise')->exists())->toBeTrue()
        ->and(Exercise::where('slug', 'barbell-seated-calf-raise-1371')->exists())->toBeTrue();
});

test('reseeding is idempotent', function () {
    $this->seed(ExerciseDatabaseSeeder::class);

    expect(Exercise::count())->toBe(1324);
});
