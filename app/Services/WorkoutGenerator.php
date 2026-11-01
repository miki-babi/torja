<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use App\Support\ExerciseCatalog;

/**
 * Builds the member's workout from the muscle and equipment picks made in
 * the /today builder. Generated playlists are marked `generated_for` today
 * so they stay out of the "my playlists" pickers, and unused ones are
 * pruned the next day.
 */
class WorkoutGenerator
{
    private const EXERCISES_PER_WORKOUT = 6;

    /**
     * Create a generated playlist matching the picks, or null when no
     * exercise satisfies them. An empty muscle list means "full body";
     * an empty equipment list means "any equipment".
     *
     * @param  array<int, string>  $bodyParts
     * @param  array<int, string>  $equipment
     */
    public function build(User $user, array $bodyParts, array $equipment): ?ExercisePlaylist
    {
        $parts = $this->expandParts($bodyParts);

        $exerciseIds = Exercise::query()
            ->when($parts !== [], fn ($q) => $q->whereIn('body_part', $parts))
            ->when($equipment !== [], fn ($q) => $q->whereIn('equipment', $equipment))
            ->inRandomOrder()
            ->limit(self::EXERCISES_PER_WORKOUT)
            ->pluck('slug')
            ->all();

        if ($exerciseIds === []) {
            return null;
        }

        $playlist = $user->exercisePlaylists()->create([
            'name' => $this->nameFor($parts),
            'generated_for' => today(),
        ]);

        foreach ($exerciseIds as $position => $exerciseId) {
            $playlist->items()->create([
                'exercise_id' => $exerciseId,
                'position' => $position + 1,
            ]);
        }

        $this->pruneStale($user);

        return $playlist->load('items');
    }

    /**
     * Expand grouped picker ids ('arms' covers upper + lower arms) and drop
     * anything that isn't a catalog body part — an empty result means the
     * workout draws from every body part.
     *
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    private function expandParts(array $parts): array
    {
        $expanded = [];

        foreach ($parts as $part) {
            foreach (ExerciseCatalog::GROUPED_PARTS[$part] ?? [$part] as $resolved) {
                if (in_array($resolved, ExerciseCatalog::BODY_PARTS, true)) {
                    $expanded[] = $resolved;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Playlist name from the picked muscles, e.g. "Chest + Back workout"
     * or "Full body workout" when nothing was picked.
     *
     * @param  array<int, string>  $parts
     */
    private function nameFor(array $parts): string
    {
        if ($parts === []) {
            return 'Full body workout';
        }

        $labels = array_map(
            fn (string $part): string => ExerciseCatalog::label($part),
            $parts,
        );

        return implode(' + ', $labels).' workout';
    }

    /**
     * Drop generated playlists from previous days that were never used in a
     * session, so unworked suggestions don't accumulate forever.
     */
    private function pruneStale(User $user): void
    {
        $user->exercisePlaylists()
            ->whereNotNull('generated_for')
            ->whereDate('generated_for', '<', today())
            ->whereDoesntHave('workoutSessions')
            ->delete();
    }
}
