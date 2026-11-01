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

        return Inertia::render('member/home', [
            'userName' => Str::before(trim((string) $user?->name), ' ') ?: 'there',
            'streak' => $user->activityStreak(),
            'banners' => HomeBanners::forMember(),
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
