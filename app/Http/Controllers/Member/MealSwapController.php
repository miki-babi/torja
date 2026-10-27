<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MealPlanItem;
use App\Models\MealPlanItemOverride;
use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\ProfileResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealSwapController extends Controller
{
    /**
     * Persist the member's chosen swap for a plan item.
     */
    public function store(Request $request, MealPlanMeal $mealPlanMeal, MealPlanItem $item): JsonResponse
    {
        [$profile] = $this->authorize($request, $mealPlanMeal, $item);

        $validated = $request->validate([
            'food_id' => ['required', 'integer'],
        ]);

        $allowedIds = $mealPlanMeal->swaps->pluck('id')
            ->concat($item->food?->swaps->pluck('id') ?? collect())
            ->unique()
            ->all();

        abort_unless(in_array((int) $validated['food_id'], $allowedIds, true), 422);

        MealPlanItemOverride::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'meal_plan_item_id' => $item->id,
            ],
            ['food_id' => (int) $validated['food_id']],
        );

        app(Tracker::class)->record('meal.swap_chosen', properties: [
            'meal_plan_item_id' => $item->id,
            'food_id' => (int) $validated['food_id'],
        ]);

        return $this->mealResponse($profile, $mealPlanMeal);
    }

    /**
     * Reset an item back to its original food.
     */
    public function destroy(Request $request, MealPlanMeal $mealPlanMeal, MealPlanItem $item): JsonResponse
    {
        [$profile] = $this->authorize($request, $mealPlanMeal, $item);

        MealPlanItemOverride::query()
            ->where('user_id', $request->user()->id)
            ->where('meal_plan_item_id', $item->id)
            ->delete();

        app(Tracker::class)->record('meal.swap_reset', properties: ['meal_plan_item_id' => $item->id]);

        return $this->mealResponse($profile, $mealPlanMeal);
    }

    /**
     * Gate the request the same way MealShowController does and resolve the
     * member's profile.
     *
     * @return array{0: NutritionProfile}
     */
    private function authorize(Request $request, MealPlanMeal $mealPlanMeal, MealPlanItem $item): array
    {
        abort_unless($item->meal_plan_meal_id === $mealPlanMeal->id, 404);

        $user = $request->user();
        $plan = $mealPlanMeal->mealPlan;

        $canAccess = $plan->user_id !== null
            ? $plan->user_id === $user->id
            : ($mealPlanMeal->day === 1 || $user->isPro());

        abort_unless($canAccess, 403);

        $profile = app(ProfileResolver::class)->for($request);

        return [$profile];
    }

    /**
     * Refresh the resolved meal payload so the client can re-render in place.
     */
    private function mealResponse(NutritionProfile $profile, MealPlanMeal $mealPlanMeal): JsonResponse
    {
        $mealPlanMeal->unsetRelation('items')->unsetRelation('swaps');

        return response()->json([
            'meal' => app(MealPlanPreviewBuilder::class)->mealFor($mealPlanMeal, $profile),
        ]);
    }
}
