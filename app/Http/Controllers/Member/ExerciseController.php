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
            ->withExists('recap')
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $coachPrograms = $user->trainers()
            ->with(['user', 'programs' => fn ($query) => $query->whereNotNull('share_token')])
            ->get()
            ->flatMap(fn ($trainer) => $trainer->programs->map(
                fn ($program): array => [
                    'id' => $program->id,
                    'name' => $program->localizedName(),
                    'coach' => $trainer->display_name,
                    'image_url' => $program->image_url,
                    'price_etb' => $program->price_etb,
                    'is_free' => $program->isFree(),
                    'share_url' => $program->shareUrl(),
                ],
            ))
            ->values();

        $claimedIds = $user->programAccess()
            ->whereIn('exercise_program_id', $coachPrograms->pluck('id'))
            ->pluck('exercise_program_id')
            ->flip();

        return Inertia::render('member/exercise', [
            'playlists' => $playlists->map->toFrontend()->all(),
            'coachPrograms' => $coachPrograms->map(fn (array $program): array => [
                ...$program,
                'claimed' => $claimedIds->has($program['id']),
            ])->all(),
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
