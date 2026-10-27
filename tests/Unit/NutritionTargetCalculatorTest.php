<?php

use App\Models\NutritionProfile;
use App\Services\Nutrition\NutritionTargetCalculator;
use Illuminate\Support\Carbon;

function targetProfile(array $sections): NutritionProfile
{
    return new NutritionProfile($sections);
}

function baseProfile(array $overrides = []): NutritionProfile
{
    return targetProfile(array_merge([
        'body' => [
            'age' => 30,
            'height_cm' => 180,
            'weight_kg' => 80,
            'sex' => 'male',
            'weight_trend' => 'stable',
            'medical_flags' => ['none'],
        ],
    ], $overrides));
}

test('returns null when body data is incomplete', function () {
    $calculator = new NutritionTargetCalculator;

    expect($calculator->for(targetProfile([])))->toBeNull();
    expect($calculator->for(targetProfile([
        'body' => ['age' => 30, 'height_cm' => 180],
    ])))->toBeNull();
});

test('computes mifflin st jeor bmr for males', function () {
    // 10*80 + 6.25*180 - 5*30 + 5 = 1780
    $result = (new NutritionTargetCalculator)->for(baseProfile());

    expect($result['bmr'])->toBe(1780);
});

test('derives macro targets from kcal and protein', function () {
    $result = (new NutritionTargetCalculator)->for(baseProfile());

    // tdee 1780 * 1.2 = 2136, general goal, protein 80 * 1.4 = 112
    expect($result['kcal_target'])->toBe(2136);
    expect($result['protein_g'])->toBe(112);
    // fat = 30% of kcal / 9 = 71, carbs = remaining kcal / 4
    expect($result['fat_g'])->toBe(71);
    expect($result['carbs_g'])->toBe(262);
});

test('computes mifflin st jeor bmr for females', function () {
    $profile = baseProfile();
    $profile->body = array_merge($profile->body, ['sex' => 'female']);

    // 1780 - 166 = 1614
    $result = (new NutritionTargetCalculator)->for($profile);

    expect($result['bmr'])->toBe(1614);
});

test('not_say sex averages male and female bmr', function () {
    $profile = baseProfile();
    $profile->body = array_merge($profile->body, ['sex' => 'not_say']);

    // (1780 + 1614) / 2 = 1697
    $result = (new NutritionTargetCalculator)->for($profile);

    expect($result['bmr'])->toBe(1697);
});

test('applies activity factors and the exercise bump', function () {
    $calculator = new NutritionTargetCalculator;

    // Sedentary default: 1780 * 1.2 = 2136
    expect($calculator->for(baseProfile())['tdee'])->toBe(2136);

    // Moderate + 3_5 exercise: 1780 * (1.55 + 0.075) = 2892.5 -> 2893
    $result = $calculator->for(baseProfile([
        'activity' => ['daily_activity' => 'moderate', 'exercise_days' => '3_5'],
    ]));
    expect($result['tdee'])->toBe(2893);
});

test('applies goal adjustments and protein per kg', function (string $goal, int $adjustment, float $proteinPerKg) {
    $result = (new NutritionTargetCalculator)->for(baseProfile([
        'goal' => ['primary_goal' => $goal],
    ]));

    expect($result['kcal_target'])->toBe((int) round(2136 + $adjustment));
    expect($result['protein_g'])->toBe((int) round(80 * $proteinPerKg));
})->with([
    'lose_fat' => ['lose_fat', -500, 1.8],
    'recomp' => ['recomp', -250, 2.0],
    'build_muscle' => ['build_muscle', 300, 1.8],
    'maintain' => ['maintain', 0, 1.4],
    'general' => ['general', 0, 1.4],
]);

test('warns for minors', function () {
    $profile = baseProfile();
    $profile->body = array_merge($profile->body, ['age' => 16]);

    $types = collect((new NutritionTargetCalculator)->for($profile)['warnings'])
        ->pluck('type');

    expect($types)->toContain('minor');
});

test('warns for medical flags', function () {
    $profile = baseProfile();
    $profile->body = array_merge($profile->body, ['medical_flags' => ['pregnancy']]);

    $types = collect((new NutritionTargetCalculator)->for($profile)['warnings'])
        ->pluck('type');

    expect($types)->toContain('medical');
});

test('does not warn for the none medical flag', function () {
    $warnings = (new NutritionTargetCalculator)->for(baseProfile())['warnings'];

    expect(collect($warnings)->pluck('type'))->not->toContain('medical');
});

test('warns when a low-bmi user picks a losing goal', function () {
    $profile = baseProfile(['goal' => ['primary_goal' => 'lose_fat']]);
    $profile->body = array_merge($profile->body, ['weight_kg' => 50]); // BMI ~15.4

    $types = collect((new NutritionTargetCalculator)->for($profile)['warnings'])
        ->pluck('type');

    expect($types)->toContain('bmi_low');
});

test('warns when the target implies over 1 kg per week', function () {
    $profile = baseProfile([
        'goal' => ['primary_goal' => 'lose_fat', 'target_weight_kg' => 60],
        'pace' => ['target_date' => Carbon::now()->addWeeks(10)->toDateString()],
    ]);

    $types = collect((new NutritionTargetCalculator)->for($profile)['warnings'])
        ->pluck('type');

    expect($types)->toContain('rapid_change');
});

test('warns for a target date in the past', function () {
    $profile = baseProfile([
        'goal' => ['primary_goal' => 'lose_fat', 'target_weight_kg' => 75],
        'pace' => ['target_date' => Carbon::now()->subWeek()->toDateString()],
    ]);

    $types = collect((new NutritionTargetCalculator)->for($profile)['warnings'])
        ->pluck('type');

    expect($types)->toContain('unrealistic_date');
});
