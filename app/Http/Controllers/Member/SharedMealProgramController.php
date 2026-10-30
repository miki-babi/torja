<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\MealProgram;
use App\Models\MealProgramMeal;
use App\Services\Programs\MealProgramAccessGranter;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SharedMealProgramController extends Controller
{
    public function __construct(
        private readonly MealProgramAccessGranter $grants,
    ) {}

    /**
     * Public read-only view of a shared meal program, addressed by its share
     * token. Guests can view; claiming needs login and Pro.
     */
    public function show(Request $request, MealProgram $mealProgram): Response
    {
        $mealProgram->load(['meals.items.food', 'user']);

        $user = $request->user();

        app(Tracker::class)->record('shared_meal_program.viewed', subject: $mealProgram, properties: [
            'owner_id' => $mealProgram->user_id,
            'is_owner' => $user?->id === $mealProgram->user_id,
        ]);

        $claimedPlan = $user !== null
            ? $this->grants->claimedPlan($user, $mealProgram)
            : null;

        return Inertia::render('member/shared-meal-program', [
            'program' => [
                'name' => $mealProgram->localizedName(),
                'owner' => $mealProgram->user->name,
                'description' => $mealProgram->localizedDescription(),
                'image_url' => $mealProgram->image_url,
                'highlights' => $mealProgram->localizedHighlights(),
                'days' => $mealProgram->meals
                    ->groupBy('day')
                    ->map(fn ($meals, int $day): array => [
                        'day' => $day,
                        'meals' => $meals
                            ->map(fn ($meal): array => [
                                'type' => $meal->meal_type->value,
                                'items' => $this->mealItems($meal),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'isOwner' => $user?->id === $mealProgram->user_id,
            'isPro' => $user?->isPro() ?? false,
            'alreadyClaimed' => $claimedPlan !== null || ($user !== null && $this->grants->hasAccess($user, $mealProgram)),
            'saveUrl' => route('meal-programs.shared.save', $mealProgram->share_token),
            'proUrl' => route('pro.show'),
            'homeUrl' => $user !== null ? route('member.home') : route('explore.index'),
            'editUrl' => $user?->isAdmin() ? route('admin.meal-programs.edit', $mealProgram) : null,
            'claimedPlanUrl' => $claimedPlan !== null
                ? route('member.meal-programs.show', $claimedPlan)
                : null,
        ]);
    }

    /**
     * Claim a meal program: record the access row and copy its days into a
     * member-owned meal plan. Requires Pro; idempotent.
     */
    public function save(Request $request, MealProgram $mealProgram): JsonResponse
    {
        abort_unless($request->user()->isPro(), 403);

        $granted = $this->grants->grant($request->user(), $mealProgram);

        if ($granted) {
            app(Tracker::class)->record('shared_meal_program.saved', subject: $mealProgram, properties: [
                'owner_id' => $mealProgram->user_id,
            ]);
        }

        $plan = $this->grants->claimedPlan($request->user(), $mealProgram);

        return response()->json([
            'claimed' => true,
            'plan_url' => $plan !== null ? route('member.meal-programs.show', $plan) : null,
        ], $granted ? 201 : 200);
    }

    /**
     * @return array<int, array{name: string, image_url: string|null, portion_note: string|null, macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null}>
     */
    private function mealItems(MealProgramMeal $meal): array
    {
        return $meal->items
            ->map(fn ($item): array => [
                'name' => $item->food->name_en ?? 'Unknown food',
                'image_url' => $item->food?->image_url,
                'portion_note' => $item->portion_note,
                'macros' => $this->macros($item->food),
            ])
            ->values()
            ->all();
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
}
