<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Muscle-balance radar card on the Progress page, plus any missing
     * body-part axis labels used by the picker keys.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.progress' => [
            'muscle_balance' => ['en' => 'Muscle balance · 7 days', 'am' => 'የጭንቅላት ሚዛን · 7 ቀናት'],
            'muscle_empty' => ['en' => 'Log a workout this week to see your muscle balance.', 'am' => 'የጭንቅላት ሚዛንዎን ለማየት በዚህ ሳምንት ክስለት ይመዝግቡ።'],
        ],
        'exercise_values.body_part' => [
            'chest' => ['en' => 'Chest', 'am' => 'የደረት'],
            'back' => ['en' => 'Back', 'am' => 'የጀርባ'],
            'shoulders' => ['en' => 'Shoulders', 'am' => 'ትከሻዎች'],
            'upper arms' => ['en' => 'Upper arms', 'am' => 'የክንድ'],
            'lower arms' => ['en' => 'Forearms', 'am' => 'ምድዋድ'],
            'waist' => ['en' => 'Core', 'am' => 'ሆድ'],
            'upper legs' => ['en' => 'Upper legs', 'am' => 'የእግር ላይኛ'],
            'lower legs' => ['en' => 'Calves', 'am' => 'ጭን'],
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
};
