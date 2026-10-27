<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;
use Illuminate\Http\Request;

/**
 * Resolves the NutritionProfile for the current request: bound to the
 * authenticated user, or to the session for guests. A session-bound profile
 * is claimed by the user on their first request while authenticated.
 *
 * The profile id is also kept in the session itself because login and
 * registration regenerate the session id — a lookup by session id alone
 * would orphan the guest's answers at exactly the signup boundary.
 */
class ProfileResolver
{
    private const SESSION_KEY = 'nutrition_profile_id';

    public function for(Request $request): NutritionProfile
    {
        $user = $request->user();

        if ($user !== null) {
            $profile = NutritionProfile::where('user_id', $user->id)->first();

            if ($profile !== null) {
                return $profile;
            }

            $sessionProfile = $this->claimableSessionProfile($request);

            if ($sessionProfile !== null) {
                $sessionProfile->update([
                    'user_id' => $user->id,
                    'session_id' => null,
                ]);

                return $sessionProfile;
            }

            return NutritionProfile::create(['user_id' => $user->id]);
        }

        $profile = NutritionProfile::firstOrCreate([
            'session_id' => $request->session()->getId(),
            'user_id' => null,
        ]);

        if ($request->session()->get(self::SESSION_KEY) !== $profile->id) {
            $request->session()->put(self::SESSION_KEY, $profile->id);
        }

        return $profile;
    }

    /**
     * The guest profile that may be claimed: prefer the id stashed in the
     * (possibly regenerated) session, fall back to the raw session id for
     * profiles created before the stash existed.
     */
    private function claimableSessionProfile(Request $request): ?NutritionProfile
    {
        $profileId = $request->session()->get(self::SESSION_KEY);

        if (is_int($profileId) || is_string($profileId)) {
            $profile = NutritionProfile::where('id', $profileId)
                ->whereNull('user_id')
                ->first();

            if ($profile !== null) {
                return $profile;
            }
        }

        return NutritionProfile::where('session_id', $request->session()->getId())
            ->whereNull('user_id')
            ->first();
    }
}
