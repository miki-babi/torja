<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\ProfileResolver;
use App\Support\ProPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionPreviewController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly NutritionTargetCalculator $targets,
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Show the computed plan preview behind the signup wall.
     */
    public function show(Request $request): Response
    {
        $profile = $this->profiles->for($request);

        $targets = $this->targets->for($profile);

        $mealPlan = $request->user()->mealPlans()->latest('id')->first();

        return Inertia::render('plan/preview', [
            'targets' => $targets,
            'warnings' => $targets['warnings'] ?? [],
            'sampleDay' => $this->preview->for($profile),
            'unlocked' => $request->user()->isPro(),
            'mealPlanUrl' => $mealPlan !== null
                ? route('member.meal-programs.show', $mealPlan)
                : null,
            'price' => ProPage::price(),
        ]);
    }
}
