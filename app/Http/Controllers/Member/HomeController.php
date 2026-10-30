<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\DailyWorkoutGenerator;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\ProfileResolver;
use App\Support\ExerciseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly MealPlanPreviewBuilder $preview,
        private readonly DailyWorkoutGenerator $workout,
    ) {}

    /**
     * Member home: greeting, today's meal-plan checklist and today's workout.
     */
    public function __invoke(Request $request): Response
    {
        $profile = $this->profiles->for($request);

        // Map the weekday onto the plan's 7 days until real start-date
        // tracking exists. Locked users only ever see the day-one sample.
        $day = $request->user()->isPro()
            ? Carbon::now()->dayOfWeekIso
            : 1;

        $playlist = $this->workout->for($request->user());
        $muscle = $playlist->items->first() !== null
            ? ExerciseCatalog::muscleFor($playlist->items->first()->exercise_id)
            : null;

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $request->user()?->name), ' ') ?: 'there',
            'planDay' => $this->preview->for($profile, $day),
            'todayWorkout' => [
                'title' => $playlist->name,
                'subtitle' => ($muscle === null ? 'Full body' : ExerciseCatalog::label($muscle))
                    .' · '.$playlist->items->count().' exercises',
            ],
            'streak' => $request->user()->activityStreak(),
        ]);
    }
}
