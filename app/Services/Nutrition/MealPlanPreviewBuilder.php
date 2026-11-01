<?php

namespace App\Services\Nutrition;

use App\Models\MealPlan;
use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;

/**
 * Builds member-facing meal payloads: the items a plan meal lists, rendered
 * from each item's stored raw_text snapshot. Members never resolve food
 * database records — plan items carry their display name from creation
 * time (seeded plans, claimed program copies, or member-typed items).
 */
class MealPlanPreviewBuilder
{
    /**
     * @return array{plan_name: string, day: int, meals: array<int, array{id: int, type: string, items: array<int, array{item_id: int, name: string, portion_note: string|null}>}>}|null
     */
    public function for(NutritionProfile $profile, int $day = 1): ?array
    {
        $plan = MealPlan::where('slug', $this->planSlug($profile))->first();

        if ($plan === null) {
            return null;
        }

        return $this->dayFor($plan, $day);
    }

    /**
     * A single day's meal payload for a specific plan — used by for() for
     * the profile-matched plan and by the member meal-program viewer for
     * member-owned plan copies.
     *
     * @return array{plan_name: string, day: int, meals: array<int, array{id: int, type: string, items: array<int, array{item_id: int, name: string, portion_note: string|null}>}>}
     */
    public function dayFor(MealPlan $plan, int $day): array
    {
        $meals = $plan->meals()
            ->where('day', $day)
            ->with('items')
            ->get();

        return [
            'plan_name' => $plan->name,
            'day' => $day,
            'meals' => $meals
                ->map(fn (MealPlanMeal $meal) => $this->mealFor($meal))
                ->all(),
        ];
    }

    /**
     * Payload for a single plan meal: the stored item names and portion
     * notes, in plan order.
     *
     * @return array{id: int, type: string, items: array<int, array{item_id: int, name: string, portion_note: string|null}>}
     */
    public function mealFor(MealPlanMeal $meal): array
    {
        $meal->loadMissing('items');

        return [
            'id' => $meal->id,
            'type' => $meal->meal_type->value,
            'items' => $meal->items
                ->map(fn ($item): array => [
                    'item_id' => $item->id,
                    'name' => $item->raw_text,
                    'portion_note' => $item->portion_note,
                ])
                ->all(),
        ];
    }

    /**
     * Map the wizard's primary goal onto a seeded plan slug.
     */
    private function planSlug(NutritionProfile $profile): string
    {
        return app(PlanMatcher::class)->slugFor($profile);
    }
}
