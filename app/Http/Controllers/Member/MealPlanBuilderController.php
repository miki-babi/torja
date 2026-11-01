<?php

namespace App\Http\Controllers\Member;

use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Support\ProgramCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanBuilderController extends Controller
{
    /**
     * Step-by-step meal plan builder: name + day count, then per-day meals
     * and food items, saved through member.meal-plans.store. The name step
     * also surfaces the published meal programs for discovery.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('member/meal-plan-builder', [
            'mealTypes' => array_column(MealType::cases(), 'value'),
            'maxDays' => 7,
            'mealPrograms' => ProgramCatalog::mealPrograms($request->user()),
        ]);
    }
}
