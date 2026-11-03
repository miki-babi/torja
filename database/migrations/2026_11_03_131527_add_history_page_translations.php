<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Workout history page empty state. The title and "view all" strings
     * reuse member.progress.history and member.records.view_all.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.history' => [
            'empty' => ['en' => 'No workouts logged yet — finish a session and it will show here.', 'am' => 'ገና ክስለት አልተመዘገበም — ክስለት ይጨርሱ እና እዚህ ይታያል።'],
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
