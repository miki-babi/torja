<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    /**
     * Exercise explore/track screen. Filters and the first result page come
     * from the exercises table; playlists and recent sessions from the user's
     * data. The explore list loads further pages from member.exercises.search.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $playlists = $user->exercisePlaylists()
            ->whereNull('generated_for')
            ->with(['items', 'program'])
            ->get();

        $sessions = $user->workoutSessions()
            ->with('sets')
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $exerciseIds = $playlists->flatMap->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        return Inertia::render('member/exercise', [
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
            'equipment' => Exercise::query()->distinct()->orderBy('equipment')->pluck('equipment')->all(),
            'levels' => ['beginner', 'intermediate', 'advanced'],
            'initialExercises' => Exercise::query()
                ->orderBy('name')
                ->limit(30)
                ->get()
                ->map->toSummary()
                ->all(),
            'initialTotal' => Exercise::count(),
            'weightLogs' => $user->weightLogs()->limit(60)->get(),
        ]);
    }
}
