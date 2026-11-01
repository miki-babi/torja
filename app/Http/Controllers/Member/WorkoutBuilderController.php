<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Services\WorkoutGenerator;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkoutBuilderController extends Controller
{
    public function __construct(
        private readonly WorkoutGenerator $generator,
    ) {}

    /**
     * Build a workout from the muscle and equipment picks made on /today
     * and start today's session on the generated playlist.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'muscles' => ['nullable', 'array'],
            'muscles.*' => ['string', Rule::in([...ExerciseCatalog::BODY_PARTS, ...array_keys(ExerciseCatalog::GROUPED_PARTS)])],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', Rule::in(Exercise::query()->distinct()->pluck('equipment')->all())],
        ]);

        $playlist = $this->generator->build(
            $request->user(),
            $data['muscles'] ?? [],
            $data['equipment'] ?? [],
        );

        if ($playlist === null) {
            return response()->json([
                'message' => __('member.builder.no_matches'),
            ], 422);
        }

        $session = WorkoutSession::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->where('exercise_playlist_id', $playlist->id)
            ->first();

        $resumed = $session !== null;

        $session ??= WorkoutSession::create([
            'user_id' => $request->user()->id,
            'date' => now()->toDateString(),
            'exercise_playlist_id' => $playlist->id,
        ]);

        app(Tracker::class)->record('session.started', subject: $session, properties: [
            'playlist_id' => $playlist->id,
            'resumed' => $resumed,
            'built' => true,
        ]);

        return response()->json([
            'session' => $session->toFrontend(),
            'playlist' => $playlist->toFrontend(),
        ], 201);
    }
}
