<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the /today workout builder wizard. Existing locale text is
     * never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.builder' => [
            'title' => ['en' => 'Workout builder', 'am' => 'ልምምድ ገንቢ'],
            'subtitle' => ['en' => 'Pick your muscles and equipment', 'am' => 'አካላት እና መሳሪያ ይምረጡ'],
            'step' => ['en' => 'Step :current of :total', 'am' => 'ደረጃ :current ከ :total'],
            'step_muscle' => ['en' => 'Choose target muscles', 'am' => 'የሚስሩትን አካላት ይምረጡ'],
            'continue' => ['en' => 'Continue', 'am' => 'ቀጥሉ'],
            'back' => ['en' => 'Back', 'am' => 'ተመለሱ'],
            'no_matches' => ['en' => 'No exercises match those picks — try different filters.', 'am' => 'ከምርጫዎችዎ ጋር የሚዛመድ ልምምድ የለም። ሌላ ይሞክሩ።'],
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
