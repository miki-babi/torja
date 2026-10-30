<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Missing lines for the exercise explore screen: grouped body-part tiles
     * and equipment picker chrome. Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'exercise_values' => [
            'body_part.arms' => ['en' => 'Arms', 'am' => 'ክንዶች'],
            'body_part.full body' => ['en' => 'Full body', 'am' => 'ሙሉ ሰውነት'],
        ],
        'member.exercise' => [
            'choose_equipment' => ['en' => 'Choose equipment', 'am' => 'መሳሪያ ይምረጡ'],
            'choose_level' => ['en' => 'Choose level', 'am' => 'ደረጃ ይምረጡ'],
            'level_all' => ['en' => 'All', 'am' => 'ሁሉም'],
            'other_equipment' => ['en' => 'Other', 'am' => 'ሌላ'],
            'other_equipment_title' => ['en' => 'Other equipment', 'am' => 'ሌሎች መሳሪያዎች'],
            'other_equipment_desc' => ['en' => 'Search the remaining equipment types. Select as many as you like.', 'am' => 'ቀሪውን የመሳሪያ አይነቶች ይፈልጉ። እስከፈለጉ ይምረጡ።'],
            'search_equipment' => ['en' => 'Search equipment…', 'am' => 'መሳሪያ ፈልግ…'],
            'no_equipment_matches' => ['en' => 'No equipment matches.', 'am' => 'ምንም መሳሪያ አልተገኘም።'],
            'fallback_title' => ['en' => 'Try these :part exercises instead', 'am' => 'እነዚህን :part ልምምዶች ይሞክሩ'],
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
