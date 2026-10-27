<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;
use Illuminate\Support\Carbon;

/**
 * Computes BMR/TDEE (Mifflin-St Jeor), a calorie target and protein target
 * from a nutrition profile's body, activity and goal sections. Returns null
 * when the required body data is missing.
 */
class NutritionTargetCalculator
{
    /**
     * @return array{bmr: int, tdee: int, kcal_target: int, protein_g: int, carbs_g: int, fat_g: int, warnings: array<int, array{type: string, message: string}>}|null
     */
    public function for(NutritionProfile $profile): ?array
    {
        $body = $profile->body ?? [];

        $age = $body['age'] ?? null;
        $heightCm = $body['height_cm'] ?? null;
        $weightKg = $body['weight_kg'] ?? null;
        $sex = $body['sex'] ?? null;

        if (! is_numeric($age) || ! is_numeric($heightCm) || ! is_numeric($weightKg) || $sex === null) {
            return null;
        }

        $age = (int) $age;
        $heightCm = (float) $heightCm;
        $weightKg = (float) $weightKg;

        $bmrMale = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) + 5;
        $bmrFemale = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) - 161;

        $bmr = match ($sex) {
            'male' => $bmrMale,
            'female' => $bmrFemale,
            default => ($bmrMale + $bmrFemale) / 2,
        };

        $activity = $profile->activity ?? [];

        $factor = match ($activity['daily_activity'] ?? 'sedentary') {
            'light' => 1.375,
            'moderate' => 1.55,
            'heavy' => 1.725,
            default => 1.2,
        };

        if (in_array($activity['exercise_days'] ?? 'none', ['3_5', '6_7'], true)) {
            $factor += 0.075;
        }

        $tdee = $bmr * min($factor, 1.9);

        $goal = $profile->goal ?? [];
        $primaryGoal = $goal['primary_goal'] ?? 'general';

        $kcalTarget = $tdee + match ($primaryGoal) {
            'lose_fat' => -500,
            'recomp' => -250,
            'build_muscle' => 300,
            default => 0,
        };

        $proteinPerKg = match ($primaryGoal) {
            'recomp' => 2.0,
            'lose_fat', 'build_muscle' => 1.8,
            default => 1.4,
        };

        $kcalTargetRounded = (int) round(max($kcalTarget, 0));
        $proteinG = (int) round($weightKg * $proteinPerKg);
        $fatG = (int) round($kcalTargetRounded * 0.30 / 9);
        $carbsG = (int) round(max(($kcalTargetRounded - ($proteinG * 4) - ($fatG * 9)) / 4, 0));

        return [
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
            'kcal_target' => $kcalTargetRounded,
            'protein_g' => $proteinG,
            'carbs_g' => $carbsG,
            'fat_g' => $fatG,
            'warnings' => $this->warnings($profile, $age, $heightCm, $weightKg, $primaryGoal),
        ];
    }

    /**
     * Warn-only safety flags; nothing is blocked.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function warnings(NutritionProfile $profile, int $age, float $heightCm, float $weightKg, string $primaryGoal): array
    {
        $warnings = [];

        if ($age < 18) {
            $warnings[] = [
                'type' => 'minor',
                'message' => 'You are under 18 — energy and protein estimates may not apply. Talk to a parent, doctor, or coach before changing your diet.',
            ];
        }

        $medicalFlags = array_values(array_diff($profile->body['medical_flags'] ?? [], ['none']));

        if ($medicalFlags !== []) {
            $warnings[] = [
                'type' => 'medical',
                'message' => 'You reported a medical consideration. This plan is general guidance, not medical advice — review it with a qualified professional.',
            ];
        }

        $bmi = $heightCm > 0 ? $weightKg / (($heightCm / 100) ** 2) : 0;

        if ($bmi > 0 && $bmi < 18.5 && in_array($primaryGoal, ['lose_fat', 'recomp'], true)) {
            $warnings[] = [
                'type' => 'bmi_low',
                'message' => 'Your BMI is in the underweight range, so a weight-loss target may not be appropriate. Consider a maintain or muscle-building goal.',
            ];
        }

        $targetWeight = $profile->goal['target_weight_kg'] ?? null;
        $targetDate = $profile->pace['target_date'] ?? null;

        if (is_numeric($targetWeight) && is_string($targetDate)) {
            $daysUntilTarget = Carbon::today()->diffInDays(Carbon::parse($targetDate), false);

            if ($daysUntilTarget <= 0) {
                $warnings[] = [
                    'type' => 'unrealistic_date',
                    'message' => 'Your target date is today or in the past — pick a realistic date so the plan can pace your progress safely.',
                ];
            } else {
                $weeklyChange = abs($weightKg - (float) $targetWeight) / ($daysUntilTarget / 7);

                if ($weeklyChange > 1) {
                    $warnings[] = [
                        'type' => 'rapid_change',
                        'message' => 'Your target implies changing more than ~1 kg per week, which is faster than generally recommended. A later date or a smaller goal would be safer.',
                    ];
                }
            }
        }

        return $warnings;
    }
}
