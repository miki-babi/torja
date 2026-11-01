<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Support\HomeBanners;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Member home: greeting, today's meal-plan checklist and the workout
     * builder call-to-action.
     */
    public function __invoke(Request $request): Response
    {
        $plan = $request->user()->mealPlans()
            ->whereNull('meal_program_id')
            ->latest('id')
            ->first();

        // Map the weekday onto the member's own plan days — a shorter
        // plan cycles through its days. Locked users see day one only.
        $maxDay = $plan === null ? 0 : max(1, (int) $plan->meals()->max('day'));

        $day = ($request->user()->isPro()
            ? Carbon::now()->dayOfWeekIso
            : 1);
        $day = $maxDay === 0 ? 0 : (($day - 1) % $maxDay) + 1;

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $request->user()?->name), ' ') ?: 'there',
            'planDay' => $plan === null ? null : $this->preview->dayFor($plan, $day),
            'streak' => $request->user()->activityStreak(),
            'banners' => HomeBanners::forMember(),
        ]);
    }
}
