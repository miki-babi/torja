<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Card titles on the exercise detail screen.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.exercise' => [
            'similar_muscle' => ['en' => 'Similar muscle', 'am' => 'ተመሳሳይ ጡንቻ'],
            'similar_equipment' => ['en' => 'Similar equipment', 'am' => 'ተመሳሳይ መሳሪያ'],
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
