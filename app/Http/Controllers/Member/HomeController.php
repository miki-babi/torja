<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MealPlanPreviewBuilder;
use App\Services\Nutrition\ProfileResolver;
use App\Support\HomeBanners;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly MealPlanPreviewBuilder $preview,
    ) {}

    /**
     * Member home: greeting, today's meal-plan checklist and the workout
     * builder call-to-action.
     */
    public function __invoke(Request $request): Response
    {
        $profile = $this->profiles->for($request);

        // Map the weekday onto the plan's 7 days until real start-date
        // tracking exists. Locked users only ever see the day-one sample.
        $day = $request->user()->isPro()
            ? Carbon::now()->dayOfWeekIso
            : 1;

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $request->user()?->name), ' ') ?: 'there',
            'planDay' => $this->preview->for($profile, $day),
            'streak' => $request->user()->activityStreak(),
            'banners' => HomeBanners::forMember(),
        ]);
    }
}
