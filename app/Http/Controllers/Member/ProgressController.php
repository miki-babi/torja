<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    /**
     * Exercise progress tracker: the full session history (no cap — this is
     * the analytics surface, unlike /exercise which only needs recent
     * sessions for quick-log prefill), playlists for session labels, body
     * weight logs, and the activity streak.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $playlists = $user->exercisePlaylists()
            ->with('items')
            ->get();

        $sessions = $user->workoutSessions()
            ->with('sets')
            ->withExists('recap')
            ->orderByDesc('date')
            ->get();

        $exerciseIds = $playlists->flatMap->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        app(Tracker::class)->record('progress.viewed');

        // Radar axes: training volume (weight_kg × reps) per body part
        // over the last 7 days, zero-filled so the polygon closes evenly.
        $axes = ['chest', 'back', 'shoulders', 'upper arms', 'lower arms', 'waist', 'upper legs', 'lower legs'];

        $volumes = $user->workoutSessions()
            ->join('workout_sets', 'workout_sets.workout_session_id', '=', 'workout_sessions.id')
            ->where('workout_sessions.date', '>=', today()->subDays(6))
            ->groupBy('workout_sets.exercise_id')
            ->selectRaw('workout_sets.exercise_id, SUM(workout_sets.weight_kg * workout_sets.reps) as volume')
            ->pluck('volume', 'exercise_id');

        $buckets = array_fill_keys($axes, 0.0);
        foreach ($volumes as $slug => $volume) {
            $part = ExerciseCatalog::muscleFor((string) $slug);
            if ($part !== null && array_key_exists($part, $buckets)) {
                $buckets[$part] += (float) $volume;
            }
        }

        return Inertia::render('member/progress', [
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
            'equipment' => Exercise::query()->distinct()->orderBy('equipment')->pluck('equipment')->all(),
            'weightLogs' => $user->weightLogs()->limit(90)->get(),
            'measurements' => $user->measurements()->limit(30)->get(),
            'photos' => $user->progressPhotos()->limit(30)->get()
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url(),
                    'taken_on' => $photo->taken_on->toDateString(),
                ])
                ->all(),
            'streak' => $user->activityStreak(),
            'activityWeek' => $user->activityWeek(),
            'muscleVolumes' => collect($buckets)
                ->map(fn (float $volume, string $part): array => [
                    'key' => $part,
                    'volume' => round($volume),
                ])
                ->values()
                ->all(),
        ]);
    }
}
