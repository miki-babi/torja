<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalRecordsController extends Controller
{
    /**
     * Personal records board: every exercise's best set, with when it was
     * achieved. Sessions feed the same client-side prByExercise helper the
     * progress page uses.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $sessions = $user->workoutSessions()
            ->with('sets')
            ->orderByDesc('date')
            ->get();

        $exerciseIds = $sessions->flatMap->sets->pluck('exercise_id')
            ->unique()
            ->all();

        app(Tracker::class)->record('records.viewed');

        return Inertia::render('member/records', [
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
        ]);
    }
}
