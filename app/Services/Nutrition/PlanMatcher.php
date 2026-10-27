<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;

/**
 * Maps a nutrition profile to the seeded meal-plan slug it should follow.
 * Shared between the preview builder and the traits/segmentation pipeline.
 */
class PlanMatcher
{
    /**
     * The seeded plan slug for the profile's primary goal.
     */
    public function slugFor(NutritionProfile $profile): string
    {
        $primaryGoal = $profile->goal['primary_goal'] ?? null;

        return in_array($primaryGoal, ['build_muscle', 'recomp'], true)
            ? 'muscle_gain_7day'
            : 'fat_loss_7day';
    }
}
