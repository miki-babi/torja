<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ids from the previous hardcoded catalog → dataset slugs. Mirrors
     * ExerciseCatalog::LEGACY_SLUGS, inlined so this migration stays a
     * historical snapshot.
     *
     * @var array<string, string>
     */
    private const LEGACY_SLUGS = [
        'bench-press' => 'barbell-bench-press',
        'incline-db-press' => 'dumbbell-incline-bench-press',
        'push-up' => 'push-up',
        'cable-fly' => 'cable-standing-up-straight-crossovers',
        'deadlift' => 'barbell-deadlift',
        'pull-up' => 'pull-up',
        'bent-over-row' => 'barbell-bent-over-row',
        'lat-pulldown' => 'cable-lat-pulldown-full-range-of-motion',
        'overhead-press' => 'dumbbell-standing-overhead-press',
        'lateral-raise' => 'dumbbell-lateral-raise',
        'rear-delt-fly' => 'barbell-rear-delt-raise',
        'arnold-press' => 'dumbbell-arnold-press',
        'bicep-curl' => 'dumbbell-biceps-curl',
        'tricep-dip' => 'triceps-dip',
        'hammer-curl' => 'dumbbell-hammer-curl',
        'rope-pushdown' => 'cable-pushdown-with-rope-attachment',
        'back-squat' => 'barbell-full-squat',
        'romanian-deadlift' => 'barbell-romanian-deadlift',
        'walking-lunge' => 'walking-lunge',
        'leg-press' => 'sled-45v-leg-press',
    ];

    /**
     * Rewrite legacy exercise ids in playlist items and logged sets to the
     * dataset slugs, dropping playlist items that would collide with an
     * already-canonical entry.
     */
    public function up(): void
    {
        $this->remap(self::LEGACY_SLUGS);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->remap(array_flip(self::LEGACY_SLUGS));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function remap(array $map): void
    {
        foreach ($map as $from => $to) {
            if ($from === $to) {
                continue;
            }

            DB::table('workout_sets')
                ->where('exercise_id', $from)
                ->update(['exercise_id' => $to]);

            // Drop items whose playlist already contains the target slug so the
            // (exercise_playlist_id, exercise_id) unique index is not violated.
            $colliding = DB::table('exercise_playlist_items as moved')
                ->join('exercise_playlist_items as existing', function ($join) use ($to): void {
                    $join->on('moved.exercise_playlist_id', '=', 'existing.exercise_playlist_id')
                        ->where('existing.exercise_id', '=', $to);
                })
                ->where('moved.exercise_id', $from)
                ->pluck('moved.id');

            DB::table('exercise_playlist_items')->whereIn('id', $colliding)->delete();

            DB::table('exercise_playlist_items')
                ->where('exercise_id', $from)
                ->update(['exercise_id' => $to]);
        }
    }
};
