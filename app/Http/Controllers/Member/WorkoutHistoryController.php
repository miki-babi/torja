<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutHistoryController extends Controller
{
    /**
     * Full workout history: every logged session, newest first. Tapping a
     * row deep-links back to the progress page's session card — the same
     * drill-in the preview rows on progress use.
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

        app(Tracker::class)->record('history.viewed');

        return Inertia::render('member/history', [
            'sessions' => $sessions->map->toFrontend()->all(),
            'playlists' => $playlists->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
            'equipment' => Exercise::query()->distinct()->orderBy('equipment')->pluck('equipment')->all(),
        ]);
    }
}
