<?php

namespace App\Services\Nutrition;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\MealPlanItemOverride;
use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;

/**
 * Builds a one-day sample meal plan preview for a nutrition profile: maps the
 * primary goal to a seeded plan slug, then filters day 1 items against the
 * user's exclusions (allergens, excluded ingredients, disliked foods, diet
 * flags), replacing excluded items via meal-level then food-level swaps.
 */
class MealPlanPreviewBuilder
{
    /**
     * @return array{plan_name: string, day: int, meals: array<int, array{id: int, type: string, items: array<int, array{name: string, portion_note: string|null, food_id: int|null, image_url: string|null, macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null, swapped_from: array{name: string, reason: string|null}|null, excluded: bool, original_food_id: int|null, original_image_url: string|null, original_macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null}>}>, excluded_count: int}|null
     */
    public function for(NutritionProfile $profile, int $day = 1): ?array
    {
        $plan = MealPlan::where('slug', $this->planSlug($profile))->first();

        if ($plan === null) {
            return null;
        }

        return $this->dayFor($plan, $profile, $day);
    }

    /**
     * A single day's personalized meal payload for a specific plan — used by
     * for() for the profile-matched plan and by the member meal-program
     * viewer for member-owned plan copies.
     *
     * @return array{plan_name: string, day: int, meals: array<int, array{id: int, type: string, items: array<int, array{name: string, portion_note: string|null, food_id: int|null, image_url: string|null, macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null, swapped_from: array{name: string, reason: string|null}|null, excluded: bool, original_food_id: int|null, original_image_url: string|null, original_macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null}>}>, excluded_count: int}
     */
    public function dayFor(MealPlan $plan, NutritionProfile $profile, int $day): array
    {
        $meals = $plan->meals()
            ->where('day', $day)
            ->with(['items.food.swaps', 'swaps'])
            ->get();

        $mealPayloads = $meals
            ->map(fn (MealPlanMeal $meal) => $this->mealFor($meal, $profile))
            ->all();

        $excludedCount = collect($mealPayloads)
            ->flatMap(fn (array $meal) => $meal['items'])
            ->filter(fn (array $item) => $item['excluded'] || $item['swapped_from'] !== null)
            ->count();

        return [
            'plan_name' => $plan->name,
            'day' => $day,
            'meals' => $mealPayloads,
            'excluded_count' => $excludedCount,
        ];
    }

    /**
     * Personalized payload for a single plan meal: items with the profile's
     * exclusions applied and swapped/excluded flags resolved.
     *
     * @return array{id: int, type: string, items: array<int, array{name: string, portion_note: string|null, food_id: int|null, image_url: string|null, macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null, swapped_from: array{name: string, reason: string|null}|null, excluded: bool, original_food_id: int|null, original_image_url: string|null, original_macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null}>}
     */
    public function mealFor(MealPlanMeal $meal, NutritionProfile $profile): array
    {
        $excluded = $this->exclusionPredicate($profile);

        $meal->loadMissing(['items.food.swaps', 'swaps']);

        $overrides = $profile->user_id !== null
            ? MealPlanItemOverride::query()
                ->where('user_id', $profile->user_id)
                ->whereIn('meal_plan_item_id', $meal->items->pluck('id'))
                ->with('food')
                ->get()
                ->keyBy('meal_plan_item_id')
            : collect();

        $items = $meal->items->map(function ($item) use ($meal, $excluded, $overrides): array {
            $food = $item->food;

            $swapOptions = $meal->swaps
                ->concat($food?->swaps ?? collect())
                ->unique('id')
                ->map(fn (Food $swap): array => [
                    'food_id' => $swap->id,
                    'name' => $swap->name_en,
                    'image_url' => $swap->image_url,
                    'reason' => $swap->pivot->reason ?? null,
                    'macros' => $this->macros($swap),
                    'excluded' => $excluded($swap),
                ])
                ->values()
                ->all();

            /** @var MealPlanItemOverride|null $override */
            $override = $overrides->get($item->id);

            if ($override?->food !== null) {
                return [
                    'item_id' => $item->id,
                    'name' => $override->food->name_en,
                    'portion_note' => $item->portion_note,
                    'food_id' => $override->food->id,
                    'image_url' => $override->food->image_url,
                    'macros' => $this->macros($override->food),
                    'swapped_from' => null,
                    'excluded' => false,
                    'chosen' => true,
                    'original_name' => $food?->name_en ?? $item->raw_text,
                    'original_food_id' => $food?->id,
                    'original_image_url' => $food?->image_url,
                    'original_macros' => $this->macros($food),
                    'swap_options' => $swapOptions,
                ];
            }

            if ($food === null || ! $excluded($food)) {
                return [
                    'item_id' => $item->id,
                    'name' => $food?->name_en ?? $item->raw_text,
                    'portion_note' => $item->portion_note,
                    'food_id' => $food?->id,
                    'image_url' => $food?->image_url,
                    'macros' => $this->macros($food),
                    'swapped_from' => null,
                    'excluded' => false,
                    'chosen' => false,
                    'original_name' => $food?->name_en ?? $item->raw_text,
                    'original_food_id' => $food?->id,
                    'original_image_url' => $food?->image_url,
                    'original_macros' => $this->macros($food),
                    'swap_options' => $swapOptions,
                ];
            }

            $replacement = $meal->swaps->first(fn (Food $swap): bool => ! $excluded($swap))
                ?? $food->swaps->first(fn (Food $swap): bool => ! $excluded($swap));

            if ($replacement === null) {
                return [
                    'item_id' => $item->id,
                    'name' => $food->name_en,
                    'portion_note' => $item->portion_note,
                    'food_id' => $food->id,
                    'image_url' => $food->image_url,
                    'macros' => $this->macros($food),
                    'swapped_from' => null,
                    'excluded' => true,
                    'chosen' => false,
                    'original_name' => $food->name_en,
                    'original_food_id' => $food->id,
                    'original_image_url' => $food->image_url,
                    'original_macros' => $this->macros($food),
                    'swap_options' => $swapOptions,
                ];
            }

            return [
                'item_id' => $item->id,
                'name' => $replacement->name_en,
                'portion_note' => $item->portion_note,
                'food_id' => $replacement->id,
                'image_url' => $replacement->image_url,
                'macros' => $this->macros($replacement),
                'swapped_from' => [
                    'name' => $food->name_en,
                    'reason' => $replacement->pivot->reason ?? null,
                ],
                'excluded' => false,
                'chosen' => false,
                'original_name' => $food->name_en,
                'original_food_id' => $food->id,
                'original_image_url' => $food->image_url,
                'original_macros' => $this->macros($food),
                'swap_options' => $swapOptions,
            ];
        })->all();

        return [
            'id' => $meal->id,
            'type' => $meal->meal_type->value,
            'items' => $items,
        ];
    }

    /**
     * Map the wizard's primary goal onto a seeded plan slug.
     */
    private function planSlug(NutritionProfile $profile): string
    {
        return app(PlanMatcher::class)->slugFor($profile);
    }

    /**
     * @return array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null
     */
    private function macros(?Food $food): ?array
    {
        if ($food === null) {
            return null;
        }

        return [
            'kcal_per_100g' => $food->kcal_per_100g === null ? null : (float) $food->kcal_per_100g,
            'protein_g' => $food->protein_g === null ? null : (float) $food->protein_g,
            'carbs_g' => $food->carbs_g === null ? null : (float) $food->carbs_g,
            'fat_g' => $food->fat_g === null ? null : (float) $food->fat_g,
        ];
    }

    /**
     * Build a predicate returning true when a food must be excluded for this
     * profile: allergen match, explicit dislike, or diet-pattern mismatch.
     */
    private function exclusionPredicate(NutritionProfile $profile): callable
    {
        $avoidances = $profile->avoidances ?? [];

        $allergenCodes = $avoidances['allergen_codes'] ?? [];

        $excludedIngredientIds = $avoidances['excluded_ingredient_ids'] ?? [];

        if ($excludedIngredientIds !== []) {
            $allergenCodes = array_merge(
                $allergenCodes,
                Ingredient::whereIn('id', $excludedIngredientIds)->pluck('allergens')->flatten()->all(),
            );
        }

        $allergenCodes = array_unique($allergenCodes);
        $dislikedFoodIds = array_map('intval', $avoidances['disliked_food_ids'] ?? []);

        $diet = $profile->diet ?? [];

        $requiresVegan = ($diet['diet_type'] ?? null) === 'vegan';
        $requiresVegetarian = ($diet['diet_type'] ?? null) === 'vegetarian';
        $requiresFasting = ($diet['diet_type'] ?? null) === 'certain_days'
            || (($diet['fasting'] ?? 'none') !== 'none');

        return function (Food $food) use ($allergenCodes, $dislikedFoodIds, $requiresVegan, $requiresVegetarian, $requiresFasting): bool {
            if (in_array($food->id, $dislikedFoodIds, true)) {
                return true;
            }

            if ($allergenCodes !== [] && array_intersect($food->allergens ?? [], $allergenCodes) !== []) {
                return true;
            }

            if ($requiresVegan && ! $food->is_vegan) {
                return true;
            }

            if ($requiresVegetarian && ! $food->is_vegetarian) {
                return true;
            }

            if ($requiresFasting && ! ($food->is_fasting_friendly || $food->fish_ok_fasting)) {
                return true;
            }

            return false;
        };
    }
}
