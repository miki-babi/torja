<?php

namespace App\Services\Referrals;

use App\Models\ProGrant;
use App\Models\User;
use App\Support\AmbassadorProgram;
use App\Support\Tracking\Tracker;

/**
 * Grants the "earned" Pro tier. When a member's referred-signup count
 * reaches `AmbassadorProgram::referralsForPro()`, an active
 * `referral_reward` ProGrant is created — `User::isPro()` then covers
 * them alongside paid members. Members who already hold Pro (paid or
 * granted) are skipped, and a `0` threshold disables the milestone.
 */
class ProGrantEvaluator
{
    /**
     * Check whether the ambassador just crossed the free-Pro milestone
     * and grant it if so. Called after every referral binding.
     */
    public function evaluate(User $referrer): void
    {
        $required = AmbassadorProgram::referralsForPro();

        if (! AmbassadorProgram::isEnabled() || $required <= 0 || $referrer->isPro()) {
            return;
        }

        $count = $referrer->referrals()->count();

        if ($count < $required) {
            return;
        }

        $grant = ProGrant::query()->firstOrCreate(
            ['user_id' => $referrer->id, 'revoked_at' => null],
            [
                'source' => 'referral_reward',
                'referral_count' => $count,
            ],
        );

        if ($grant->wasRecentlyCreated) {
            app(Tracker::class)->record('pro.granted', $referrer, [
                'grant_id' => $grant->id,
                'source' => $grant->source,
                'referral_count' => $grant->referral_count,
            ], $grant);
        }
    }
}
