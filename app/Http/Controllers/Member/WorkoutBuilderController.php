<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Services\WorkoutGenerator;
use App\Support\ExerciseCatalog;
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
     * and return the generated playlist so the wizard can preview it
     * before the member starts a session on it via member.sessions.store.
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

        return response()->json([
            'playlist' => $playlist->toFrontend(),
            'exerciseSummaries' => ExerciseCatalog::namesFor(
                $playlist->items->pluck('exercise_id')->all(),
            ),
        ], 201);
    }
}
