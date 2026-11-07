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

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $user?->name), ' ') ?: 'there',
            'streak' => $user->activityStreak(),
            'coaches' => $user->trainers()
                ->with('user:id,name,avatar_path')
                ->latest('trainer_clients.created_at')
                ->get()
                ->map(fn ($trainer): array => [
                    'id' => $trainer->id,
                    'name' => $trainer->display_name,
                    'avatar' => $trainer->user?->avatar,
                    'slug' => $trainer->slug,
                    'user_id' => $trainer->user_id,
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
