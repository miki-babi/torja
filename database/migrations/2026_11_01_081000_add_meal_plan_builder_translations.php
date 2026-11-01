<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the manual meal plan builder and the /meal empty state.
     * Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.meal_builder' => [
            'title' => ['en' => 'Build your meal plan', 'am' => 'የምግብ እቅድዎን ይገንቡ'],
            'subtitle' => ['en' => 'Add meals and foods for each day.', 'am' => 'ለእያንዳንዱ ቀን ምግቦችን እና ንጥረ ነገሮችን ይጨምሩ።'],
            'name_label' => ['en' => 'Plan name', 'am' => 'የእቅዱ ስም'],
            'name_placeholder' => ['en' => 'e.g. My week', 'am' => 'ለምሳሌ. የእኔ ሳምንት'],
            'days_label' => ['en' => 'How many days?', 'am' => 'ስንት ቀናት?'],
            'day_title' => ['en' => 'Day :day', 'am' => 'ቀን :day'],
            'add_meal' => ['en' => 'Add meal', 'am' => 'ምግብ ጨምር'],
            'add_food' => ['en' => 'Add food', 'am' => 'ምግብ ጨምር'],
            'search_foods' => ['en' => 'Search foods…', 'am' => 'ምግቦችን ፈልግ…'],
            'custom_item' => ['en' => 'Add custom', 'am' => 'ብጁ ጨምር'],
            'custom_placeholder' => ['en' => 'e.g. 2 boiled eggs', 'am' => 'ለምሳሌ. 2 የተቀለዱ እርጎዎች'],
            'done' => ['en' => 'Done', 'am' => 'ተጠናቅቋል'],
            'review_title' => ['en' => 'Review your plan', 'am' => 'እቅድዎን ይገምግሙ'],
            'save' => ['en' => 'Save plan', 'am' => 'እቅድ አስቀምጥ'],
            'no_plan' => ['en' => 'No meal plan yet', 'am' => 'ገና የምግብ እቅድ የለም'],
            'no_plan_hint' => ['en' => 'Build your own day-by-day plan.', 'am' => 'የራስዎን በቀን-በቀን እቅድ ይገንቡ።'],
            'create_plan' => ['en' => 'Create meal plan', 'am' => 'የምግብ እቅድ ፍጠር'],
            'item_needs_name' => ['en' => 'Each item needs a food or a name.', 'am' => 'እያንዳንዱ ንጥረ ነገር ምግብ ወይም ስም ያስፈልገዋል።'],
            'duplicate_meal_type' => ['en' => 'Each meal type can only appear once per day.', 'am' => 'እያንዳንዱ የምግብ አይነት በቀን አንድ ጊዜ ብቻ ሊመነጭ ይችላል።'],
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
