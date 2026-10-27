<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\User;
use App\Support\ExerciseCatalog;
use Carbon\CarbonInterface;

/**
 * Generates the member's workout of the day as a persisted playlist. The
 * focus muscle is the one least recently trained across the user's logged
 * sessions, so recovery is spread over the week. With no history the pick
 * falls back to a deterministic weekday rotation.
 */
class DailyWorkoutGenerator
{
    private const HISTORY_DAYS = 14;

    private const EXERCISES_PER_DAY = 6;

    public function for(User $user, ?CarbonInterface $date = null): ExercisePlaylist
    {
        $date ??= today();

        $existing = $user->exercisePlaylists()
            ->whereDate('generated_for', $date)
            ->first();

        if ($existing !== null) {
            return $existing->load('items');
        }

        $muscle = $this->focusMuscle($user, $date);

        $playlist = $user->exercisePlaylists()->create([
            'name' => ExerciseCatalog::label($muscle).' focus',
            'generated_for' => $date,
        ]);

        $exerciseIds = Exercise::query()
            ->where('body_part', $muscle)
            ->inRandomOrder()
            ->limit(self::EXERCISES_PER_DAY)
            ->pluck('slug');

        foreach ($exerciseIds as $position => $exerciseId) {
            $playlist->items()->create([
                'exercise_id' => $exerciseId,
                'position' => $position + 1,
            ]);
        }

        $this->pruneStale($user, $date);

        return $playlist->load('items');
    }

    /**
     * The muscle least recently trained in the trailing window; never-trained
     * muscles win, then oldest last-trained date, then fewest sets, then
     * catalog order. No history at all falls back to weekday rotation.
     */
    private function focusMuscle(User $user, CarbonInterface $date): string
    {
        $sessions = $user->workoutSessions()
            ->with('sets')
            ->whereDate('date', '>=', $date->copy()->subDays(self::HISTORY_DAYS))
            ->whereDate('date', '<=', $date)
            ->get();

        /** @var array<string, CarbonInterface> $lastTrainedOn */
        $lastTrainedOn = [];

        /** @var array<string, int> $setCount */
        $setCount = [];

        foreach ($sessions as $session) {
            foreach ($session->sets as $set) {
                $muscle = ExerciseCatalog::muscleFor($set->exercise_id);

                if ($muscle === null) {
                    continue;
                }

                $setCount[$muscle] = ($setCount[$muscle] ?? 0) + 1;

                if (! isset($lastTrainedOn[$muscle]) || $session->date->gt($lastTrainedOn[$muscle])) {
                    $lastTrainedOn[$muscle] = $session->date;
                }
            }
        }

        if ($lastTrainedOn === []) {
            return ExerciseCatalog::muscles()[($date->dayOfWeekIso - 1) % count(ExerciseCatalog::muscles())];
        }

        $muscles = ExerciseCatalog::muscles();

        usort($muscles, function (string $a, string $b) use ($lastTrainedOn, $setCount): int {
            $aLast = $lastTrainedOn[$a] ?? null;
            $bLast = $lastTrainedOn[$b] ?? null;

            if ($aLast === null && $bLast === null) {
                return ($setCount[$a] ?? 0) <=> ($setCount[$b] ?? 0);
            }

            if ($aLast === null) {
                return -1;
            }

            if ($bLast === null) {
                return 1;
            }

            if (! $aLast->eq($bLast)) {
                return $aLast->lt($bLast) ? -1 : 1;
            }

            return ($setCount[$a] ?? 0) <=> ($setCount[$b] ?? 0);
        });

        return $muscles[0];
    }

    /**
     * Drop generated playlists from previous days that were never used in a
     * session, so unworked daily suggestions don't accumulate forever.
     */
    private function pruneStale(User $user, CarbonInterface $date): void
    {
        $user->exercisePlaylists()
            ->whereNotNull('generated_for')
            ->whereDate('generated_for', '<', $date)
            ->whereDoesntHave('workoutSessions')
            ->delete();
    }
}
