<?php

namespace Database\Factories;

use App\Models\ExercisePlaylist;
use App\Models\ExercisePlaylistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExercisePlaylistItem>
 */
class ExercisePlaylistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_playlist_id' => ExercisePlaylist::factory(),
            'exercise_id' => fake()->slug(),
            'position' => 0,
        ];
    }
}
