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
     * Exercise explore screen. Filters and the first result page come from
     * the exercises table; playlists and recent sessions (for quick-log
     * prefill) from the user's data. The list loads further pages from
     * member.exercises.search. Progress tracking lives on member.progress.
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

        return Inertia::render('member/exercise', [
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
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
        ]);
    }
}
