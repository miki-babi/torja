<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\ProfileResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly NutritionTargetCalculator $targets,
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Nutrition screen: daily targets plus plan days so date navigation
     * happens client-side without extra requests. Locked users only get the
     * day-one sample — the full week unlocks with the plan payment.
     */
    public function __invoke(Request $request): Response
    {
        $profile = $this->profiles->for($request);

        $unlocked = $request->user()->isPro();

        app(Tracker::class)->record('meal.viewed', properties: [
            'profile_completed' => $profile->isCompleted(),
            'unlocked' => $unlocked,
        ]);

        return Inertia::render('member/meal', [
            'targets' => $this->targets->for($profile),
            'planDays' => collect($unlocked ? range(1, 7) : [1])
                ->mapWithKeys(fn (int $day) => [$day => $this->preview->for($profile, $day)])
                ->all(),
            'unlocked' => $unlocked,
            'price' => (int) config('plans.pro_price_etb'),
            'claimedPrograms' => $request->user()->mealPlans()
                ->with('sourceProgram')
                ->get()
                ->map(fn ($plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->sourceProgram?->localizedName() ?? $plan->name,
                    'days_count' => $plan->meals()->reorder()->distinct()->count('day'),
                    'image_url' => $plan->sourceProgram?->image_url,
                    'url' => route('member.meal-programs.show', $plan),
                ])
                ->all(),
        ]);
    }
}
