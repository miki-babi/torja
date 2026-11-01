<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MealPlan;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealProgramController extends Controller
{
    public function __construct(
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Member viewer for a claimed meal program: all days of the member's
     * plan copy, rendered from the stored item snapshots.
     */
    public function show(Request $request, MealPlan $mealPlan): Response
    {
        abort_unless($mealPlan->user_id === $request->user()->id, 404);

        $mealPlan->loadMissing('sourceProgram');

        app(Tracker::class)->record('meal_program.viewed', subject: $mealPlan, properties: [
            'meal_program_id' => $mealPlan->meal_program_id,
        ]);

        return Inertia::render('member/meal-program', [
            'plan' => [
                'id' => $mealPlan->id,
                'name' => $mealPlan->sourceProgram?->localizedName() ?? $mealPlan->name,
                'image_url' => $mealPlan->sourceProgram?->image_url,
            ],
            'days' => $mealPlan->meals()
                ->select('day')
                ->distinct()
                ->reorder('day')
                ->pluck('day')
                ->mapWithKeys(fn (int $day) => [$day => $this->preview->dayFor($mealPlan, $day)])
                ->all(),
        ]);
    }
}
