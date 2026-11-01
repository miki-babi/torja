<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the builder's preview step and session continue rows.
     * Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.builder' => [
            'continue_session' => ['en' => 'Continue today\'s sessions', 'am' => 'የዛሬ ልምምዶችን ቀጥሉ'],
            'free_session' => ['en' => 'Free session', 'am' => 'ነጻ ልምምድ'],
            'exercises_count' => ['en' => ':count exercises', 'am' => ':count ልምምዶች'],
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
