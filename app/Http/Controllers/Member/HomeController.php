<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\HomeBanners;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Member home: greeting, the workout builder call-to-action, and a
     * this-week progress snapshot linking to the full tracker.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $weekSessions = $user->workoutSessions()
            ->with('sets')
            ->whereDate('date', '>=', now()->startOfWeek())
            ->get();

        $recaps = RecapController::feedQuery($user)
            ->latest('id')
            ->limit(11)
            ->get();

        $hasMore = $recaps->count() > 10;
        $recaps = $recaps->take(10);
        RecapController::hydrateComments($recaps);

        $coach = $user->activeCoach();

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $user?->name), ' ') ?: 'there',
            'streak' => $user->activityStreak(),
            'coachPrograms' => $coach === null ? [] : $user->programAccess()
                ->whereHas('program', fn ($query) => $query->where('user_id', $coach->user_id))
                ->with('program:id,name,name_am,image_path')
                ->latest('id')
                ->get()
                ->map(fn ($access): array => [
                    'id' => $access->program->id,
                    'name' => $access->program->localizedName(),
                    'image_url' => $access->program->image_url,
                    'url' => route('member.programs.show', $access->program),
                ])
                ->all(),
            'coachMealPrograms' => $coach === null ? [] : $user->mealPlans()
                ->whereHas('sourceProgram', fn ($query) => $query->where('user_id', $coach->user_id))
                ->with('sourceProgram:id,user_id,name,name_am,image_path')
                ->latest('id')
                ->get()
                ->map(fn ($plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->sourceProgram?->localizedName() ?? $plan->name,
                    'image_url' => $plan->sourceProgram?->image_url,
                    'url' => route('member.meal-programs.show', $plan),
                ])
                ->all(),
            'banners' => HomeBanners::forMember(),
            'recaps' => $recaps->map(fn ($recap) => $recap->toFeed($user))->all(),
            'recapsNextCursor' => $hasMore ? $recaps->last()->id : null,
            'weekStats' => [
                'sessions' => $weekSessions->filter(
                    fn ($session) => $session->sets->isNotEmpty(),
                )->count(),
                'volume' => $weekSessions->sum(
                    fn ($session) => $session->sets->sum(
                        fn ($set) => ($set->weight_kg ?? 0) * ($set->reps ?? 0),
                    ),
                ),
            ],
        ]);
    }
}
