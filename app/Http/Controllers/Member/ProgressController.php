<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
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
            ->orderByDesc('date')
            ->get();

        $exerciseIds = $playlists->flatMap->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        app(Tracker::class)->record('progress.viewed');

        return Inertia::render('member/progress', [
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
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
        ]);
    }
}
