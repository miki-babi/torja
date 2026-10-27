<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MealPlanMeal;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\ProfileResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealShowController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Detail screen for one plan meal, personalized for the current profile's
     * exclusions and swaps.
     */
    public function __invoke(Request $request, MealPlanMeal $mealPlanMeal): Response
    {
        abort_unless(
            $this->canView($request, $mealPlanMeal),
            403,
        );

        $profile = $this->profiles->for($request);

        app(Tracker::class)->record('meal.item_viewed', subject: $mealPlanMeal, properties: [
            'day' => $mealPlanMeal->day,
            'meal_type' => $mealPlanMeal->meal_type->value,
        ]);

        return Inertia::render('member/meal-show', [
            'planName' => $mealPlanMeal->mealPlan->name,
            'day' => $mealPlanMeal->day,
            'meal' => $this->preview->mealFor($mealPlanMeal, $profile),
        ]);
    }

    /**
     * Meal visibility: member-owned plan copies (claimed meal programs) are
     * fully open to their owner; global seeded plans show day one to
     * everyone and the rest only after the plan unlock. Other members'
     * copies stay forbidden rather than hidden — the parent route model
     * binding already proves the row exists.
     */
    private function canView(Request $request, MealPlanMeal $mealPlanMeal): bool
    {
        $plan = $mealPlanMeal->mealPlan;
        $userId = $request->user()->id;

        if ($plan->user_id !== null) {
            return $plan->user_id === $userId;
        }

        return $mealPlanMeal->day === 1 || $request->user()->isPro();
    }
}
