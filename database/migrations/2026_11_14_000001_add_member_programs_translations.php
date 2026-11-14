<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Strings for the member "Free programs" browse screen.
     *
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'title' => ['en' => 'Free programs', 'am' => 'ነፃ ፕሮግራሞች'],
        'subtitle' => [
            'en' => 'Programs your coach shares for free — tap to get one.',
            'am' => 'አሰልጣኝህ በነፃ የሚያጋራቸው ፕሮግራሞች — ለማግኘት ንካ።',
        ],
        'workouts' => ['en' => 'workouts', 'am' => 'ልምምዶች'],
        'meals' => ['en' => 'meals', 'am' => 'ምግቦች'],
        'meal_plan' => ['en' => 'Meal plan', 'am' => 'የምግብ እቅድ'],
        'empty' => ['en' => 'No free programs yet', 'am' => 'ገና ነፃ ፕሮግራም የለም'],
        'empty_hint' => [
            'en' => 'When your coach shares a free program it will show up here.',
            'am' => 'አሰልጣኝህ ነፃ ፕሮግራም ሲያጋራ እዚህ ይታያል።',
        ],
        'from_coach' => ['en' => 'Free programs from :name', 'am' => 'ከ:name ነፃ ፕሮግራሞች'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'member.programs', 'key' => $key]);
            $line->text = array_merge($text, $line->text ?? []);
            $line->save();
        }

        Translator::flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LanguageLine::where('group', 'member.programs')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
