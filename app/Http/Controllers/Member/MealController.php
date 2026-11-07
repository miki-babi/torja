<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\ProfileResolver;
use App\Support\ProPage;
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

        $plan = $request->user()->mealPlans()
            ->whereNull('meal_program_id')
            ->latest('id')
            ->first();

        app(Tracker::class)->record('meal.viewed', properties: [
            'profile_completed' => $profile->isCompleted(),
            'unlocked' => $unlocked,
        ]);

        $maxDay = $plan === null ? 0 : (int) $plan->meals()->max('day');

        return Inertia::render('member/meal', [
            'targets' => $this->targets->for($profile),
            'planDays' => $plan === null ? [] : collect($unlocked ? range(1, $maxDay) : [1])
                ->mapWithKeys(fn (int $day) => [$day => $this->preview->dayFor($plan, $day)])
                ->all(),
            'unlocked' => $unlocked,
            'price' => ProPage::price(),
            'coachPrograms' => $request->user()->trainers()
                ->with(['user', 'mealPrograms' => fn ($query) => $query->whereNotNull('share_token')])
                ->get()
                ->flatMap(fn ($trainer) => $trainer->mealPrograms->map(
                    fn ($program): array => [
                        'id' => $program->id,
                        'name' => $program->localizedName(),
                        'coach' => $trainer->display_name,
                        'image_url' => $program->image_url,
                        'price_etb' => $program->price_etb,
                        'is_free' => $program->isFree(),
                        'share_url' => $program->shareUrl(),
                    ],
                ))
                ->values()
                ->all(),
            'claimedPrograms' => $request->user()->mealPlans()
                ->with('sourceProgram')
                ->get()
                ->map(fn ($plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->sourceProgram?->localizedName() ?? $plan->name,
                    'days_count' => $plan->meals()->reorder()->distinct()->count('day'),
                    'image_url' => $plan->sourceProgram?->image_url,
                    'coach' => $plan->sourceProgram?->user?->isTrainer()
                        ? $plan->sourceProgram->user->name
                        : null,
                    'url' => route('member.meal-programs.show', $plan),
                ])
                ->all(),
        ]);
    }
}
