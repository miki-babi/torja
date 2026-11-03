<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use App\Support\ProgramCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    /**
     * The workout builder: muscle and equipment pickers, plus the sessions
     * and playlists the active-session card renders.
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
            ->limit(20)
            ->get();

        $exerciseIds = $playlists->flatMap->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        $back = (string) $request->query('back', '');

        return Inertia::render('member/today', [
            'backUrl' => str_starts_with($back, '/') ? $back : route('member.home'),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
            'equipment' => Exercise::query()
                ->distinct()
                ->orderBy('equipment')
                ->pluck('equipment')
                ->all(),
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'exercisePrograms' => ProgramCatalog::exercisePrograms($user),
        ]);
    }
}
