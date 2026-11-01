<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Section titles for the "Discover programs" carousels on the meal
     * plan and workout builders' first steps. Existing locale text is
     * never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'programs' => [
            'discover_workouts' => ['en' => 'Discover workout programs', 'am' => 'የልምምድ ፕሮግራሞችን ያግኙ'],
            'discover_meals' => ['en' => 'Discover meal programs', 'am' => 'የምግብ ፕሮግራሞችን ያግኙ'],
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
