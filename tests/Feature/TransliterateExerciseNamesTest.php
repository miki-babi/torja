<?php

use App\Models\Exercise;

function datasetFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'exercises');

    file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE));

    return $path;
}

test('dry run changes neither the dataset nor the exercises table', function () {
    $path = datasetFile([
        ['id' => '0001', 'name' => 'sit up', 'name_am' => 'old value', 'translation_status' => 'machine_unreviewed'],
    ]);
    $exercise = Exercise::factory()->create(['source_id' => '0001', 'name_am' => 'old value']);

    $this->artisan('exercises:transliterate-names', ['--path' => $path])
        ->assertSuccessful();

    expect(file_get_contents($path))->toContain('old value')
        ->and($exercise->refresh()->name_am)->toBe('old value');

    unlink($path);
});

test('--write rewrites the dataset and updates matching exercises', function () {
    $path = datasetFile([
        ['id' => '0001', 'name' => 'sit up', 'name_am' => 'old value', 'translation_status' => 'machine_unreviewed'],
        ['id' => '9999', 'name' => 'barbell bench press', 'name_am' => 'x', 'translation_status' => 'machine_unreviewed'],
    ]);
    $exercise = Exercise::factory()->create(['source_id' => '0001', 'name_am' => 'old value']);
    $untouched = Exercise::factory()->create(['source_id' => 'no-match', 'name_am' => 'keep me']);

    $this->artisan('exercises:transliterate-names', ['--path' => $path, '--write' => true])
        ->assertSuccessful();

    $data = json_decode(file_get_contents($path), true);

    expect($data[0]['name_am'])->toBe('ሲት አፕ (sit up)')
        ->and($data[0]['translation_status'])->toBe('transliterated')
        ->and($data[1]['name_am'])->toBe('ባርቤል ቤንች ፕሬስ (barbell bench press)')
        ->and($exercise->refresh()->name_am)->toBe('ሲት አፕ (sit up)')
        ->and($exercise->translation_status)->toBe('transliterated')
        ->and($untouched->refresh()->name_am)->toBe('keep me');

    unlink($path);
});

test('running --write again is idempotent', function () {
    $path = datasetFile([
        ['id' => '0001', 'name' => 'sit up', 'name_am' => 'old value', 'translation_status' => 'machine_unreviewed'],
    ]);
    Exercise::factory()->create(['source_id' => '0001']);

    $this->artisan('exercises:transliterate-names', ['--path' => $path, '--write' => true]);
    $this->artisan('exercises:transliterate-names', ['--path' => $path, '--write' => true])
        ->expectsOutputToContain('0 changed')
        ->assertSuccessful();

    unlink($path);
});
