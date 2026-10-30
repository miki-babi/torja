<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Missing lines for the member home screen (greeting, streak, workout
     * card, chips, meal-plan card) plus bottom-nav and meal-type labels.
     * Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.home' => [
            'title' => ['en' => 'Home', 'am' => 'መነሻ'],
            'greeting_morning' => ['en' => 'Good morning', 'am' => 'እንደምን አደሩ'],
            'greeting_afternoon' => ['en' => 'Good afternoon', 'am' => 'እንደምን ዋሉ'],
            'greeting_evening' => ['en' => 'Good evening', 'am' => 'እንደምን አመሹ'],
            'day_streak' => ['en' => ':count day streak', 'am' => ':count ቀን ተከታታይ'],
            'today' => ['en' => 'Today', 'am' => 'ዛሬ'],
            'start_workout' => ['en' => 'Start workout', 'am' => 'ልምምድ ይጀምሩ'],
            'focus_title' => ['en' => ':muscle focus', 'am' => 'የ:muscle ልምምድ'],
            'workout_subtitle' => ['en' => ':muscle · :count exercises', 'am' => ':muscle · :count ልምምዶች'],
            'chip_exercises' => ['en' => 'Exercises', 'am' => 'ልምምዶች'],
            'chip_workout' => ['en' => 'Workout', 'am' => 'ልምምድ'],
            'chip_meal_plan' => ['en' => 'Meal plan', 'am' => 'የምግብ እቅድ'],
            'chip_programs' => ['en' => 'Programs', 'am' => 'ፕሮግራሞች'],
            'todays_meal_plan' => ['en' => "Today's meal plan", 'am' => 'የዛሬ የምግብ እቅድ'],
            'no_meal_plan' => ['en' => 'No meal plan yet.', 'am' => 'እስካሁን የምግብ እቅድ የለም።'],
        ],
        'meal_type' => [
            'breakfast' => ['en' => 'Breakfast', 'am' => 'ቁርስ'],
            'snack' => ['en' => 'Snack', 'am' => 'መክሰስ'],
            'lunch' => ['en' => 'Lunch', 'am' => 'ምሳ'],
            'post_workout' => ['en' => 'Post-workout', 'am' => 'ከልምምድ በኋላ'],
            'dinner' => ['en' => 'Dinner', 'am' => 'እራት'],
            'before_bed' => ['en' => 'Before bed', 'am' => 'ከመተኛት በፊት'],
        ],
        'nav' => [
            'home' => ['en' => 'Home', 'am' => 'መነሻ'],
            'exercise' => ['en' => 'Exercise', 'am' => 'ልምምድ'],
            'meal' => ['en' => 'Meals', 'am' => 'ምግብ'],
            'explore' => ['en' => 'Explore', 'am' => 'ያስሱ'],
            'profile' => ['en' => 'Profile', 'am' => 'መገለጫ'],
        ],
    ];

    public function up(): void
    {
        foreach (self::LINES as $group => $lines) {
            foreach ($lines as $key => $text) {
                $line = LanguageLine::firstOrNew(['group' => $group, 'key' => $key]);
                $line->text = array_merge($text, $line->text ?? []);
                $line->save();
            }
        }

        Translator::flush();
    }

    public function down(): void
    {
        foreach (self::LINES as $group => $lines) {
            LanguageLine::where('group', $group)
                ->whereIn('key', array_keys($lines))
                ->delete();
        }

        Translator::flush();
    }
};
