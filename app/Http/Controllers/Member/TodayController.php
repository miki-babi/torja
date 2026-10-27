<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\DailyWorkoutGenerator;
use App\Support\ExerciseCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __construct(
        private readonly DailyWorkoutGenerator $generator,
    ) {}

    /**
     * Today's workout: a persisted daily playlist focused on the muscle the
     * user trained least recently.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $playlist = $this->generator->for($user);

        $firstExercise = $playlist->items->first()?->exercise_id;
        $muscle = $firstExercise !== null ? ExerciseCatalog::muscleFor($firstExercise) : null;

        $lastTrainedOn = $muscle === null ? null : $user->workoutSessions()
            ->whereHas('sets', fn ($q) => $q->whereIn('exercise_id', ExerciseCatalog::idsFor($muscle)))
            ->max('date');

        $playlists = $user->exercisePlaylists()
            ->with('items')
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

        return Inertia::render('member/today', [
            'playlist' => $playlist->toFrontend(),
            'muscleFocus' => [
                'muscle' => $muscle,
                'label' => $muscle === null ? null : ExerciseCatalog::label($muscle),
                'lastTrainedOn' => $lastTrainedOn,
            ],
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
        ]);
    }
}
