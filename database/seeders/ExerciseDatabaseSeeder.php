<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports exercises.json into the exercises table.
 *
 * Rows are upserted by slug so reseeds stay stable. slugs are derived from the
 * exercise name; the six duplicated names get "-{source_id}" appended, in
 * dataset order, so the mapping is deterministic.
 */
class ExerciseDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/exercises.json');

        if (! is_file($path)) {
            throw new RuntimeException("Exercise dataset not found at [{$path}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read exercise dataset at [{$path}].");
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("Exercise dataset at [{$path}] is not a JSON array.");
        }

        /** @var array<string, true> $usedSlugs */
        $usedSlugs = [];

        $rows = collect($data)->map(function (array $row) use (&$usedSlugs): array {
            return $this->mapExercise($row, $usedSlugs);
        });

        $rows->chunk(100)->each(fn ($chunk) => Exercise::upsert(
            $chunk->all(),
            uniqueBy: ['slug'],
            update: ['source_id', 'name', 'name_am', 'category', 'body_part', 'equipment', 'target', 'muscle_group', 'secondary_muscles', 'instructions', 'instruction_steps', 'instructions_am', 'instruction_steps_am', 'translation_status', 'media_id', 'image_path', 'gif_path', 'attribution', 'exercise_type', 'level', 'location', 'movement_pattern', 'compound', 'isolation', 'full_body', 'women_home_friendly', 'goal_fat_loss', 'goal_muscle_gain', 'review_needed', 'review_reasons', 'source_created_at'],
        ));

        ExerciseCatalog::flush();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $usedSlugs
     * @return array<string, mixed>
     */
    private function mapExercise(array $row, array &$usedSlugs): array
    {
        $tags = $row['tags'];

        return [
            'source_id' => $row['id'],
            'slug' => $this->slugFor($row, $usedSlugs),
            'name' => $row['name'],
            'name_am' => $row['name_am'] ?? null,
            'category' => $row['category'],
            'body_part' => $row['body_part'],
            'equipment' => $row['equipment'],
            'target' => $row['target'],
            'muscle_group' => $row['muscle_group'],
            'secondary_muscles' => json_encode($row['secondary_muscles']),
            'instructions' => $row['instructions'],
            'instruction_steps' => json_encode($row['instruction_steps']),
            'instructions_am' => $row['instructions_am'] ?? null,
            'instruction_steps_am' => isset($row['instruction_steps_am']) ? json_encode($row['instruction_steps_am']) : null,
            'translation_status' => $row['translation_status'] ?? null,
            'media_id' => $row['media_id'],
            'image_path' => 'exercises/'.ltrim($row['image'], '/'),
            'gif_path' => 'exercises/'.ltrim($row['gif_url'], '/'),
            'attribution' => $row['attribution'],
            'exercise_type' => $tags['exercise_type'],
            'level' => $tags['level'],
            'location' => $tags['location'],
            'movement_pattern' => $tags['movement_pattern'] ?? null,
            'compound' => $tags['compound'],
            'isolation' => $tags['isolation'],
            'full_body' => $tags['full_body'],
            'women_home_friendly' => $tags['women_home_friendly'],
            'goal_fat_loss' => $tags['goal_tags']['fat_loss'],
            'goal_muscle_gain' => $tags['goal_tags']['muscle_gain'],
            'review_needed' => $row['review_needed'],
            'review_reasons' => json_encode($row['review_reasons']),
            'source_created_at' => $row['created_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $usedSlugs
     */
    private function slugFor(array $row, array &$usedSlugs): string
    {
        $slug = Str::slug($row['name']);

        if ($slug === '') {
            $slug = 'exercise';
        }

        if (isset($usedSlugs[$slug])) {
            $slug .= '-'.$row['id'];
        }

        $usedSlugs[$slug] = true;

        return $slug;
    }
}
