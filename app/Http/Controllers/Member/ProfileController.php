<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\ProfileResolver;
use App\Support\AmbassadorProgram;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileResolver $profiles,
    ) {}

    /**
     * Member profile: account info, quiz answers and the body stats. The
     * paid funnel (targets card, Pro upsell) is hidden from members.
     */
    public function __invoke(Request $request): Response
    {
        $profile = $this->profiles->for($request);
        $user = $request->user();

        return Inertia::render('member/profile', [
            'ordersUrl' => route('member.orders.index'),
            'ambassador' => [
                'enabled' => AmbassadorProgram::isEnabled(),
                'url' => route('member.ambassador'),
                'balance' => $user?->ambassadorBalance() ?? 0,
                'commission_etb' => AmbassadorProgram::commissionEtb(),
            ],
            'account' => [
                'name' => $user?->name,
                'avatar' => $user?->avatar,
                'username' => $user?->username,
                'phone' => $user?->phone,
                'email' => $user?->email,
                'member_since' => $user?->created_at?->toFormattedDateString(),
            ],
            'telegram' => [
                'connected' => ! empty($user?->telegram_chat_id),
                'username' => $user?->telegram_username,
                'available' => ! empty(config('services.telegram.bot_username')),
            ],
            'goal' => $profile->goal,
            'body' => $profile->body,
            'activity' => $profile->activity,
            'pace' => $profile->pace,
            'profileCompleted' => $profile->completed_at !== null,
            'ranksOptedIn' => (bool) $user?->leaderboard_opted_in,
            'streak' => $user->activityStreak(),
            'activityWeek' => $user->activityWeek(),
            'weightLogs' => $user->weightLogs()->limit(8)->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'logged_on' => $log->logged_on->toDateString(),
                    'weight_kg' => (float) $log->weight_kg,
                ])
                ->all(),
        ]);
    }
}
