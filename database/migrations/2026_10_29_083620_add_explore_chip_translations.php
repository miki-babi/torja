<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'chip_all' => ['en' => 'All', 'am' => 'ሁሉም'],
        'chip_workouts' => ['en' => 'Workouts', 'am' => 'ልምምዶች'],
        'chip_nutrition' => ['en' => 'Nutrition', 'am' => 'ካብ'],
        'chip_equipment' => ['en' => 'Equipment', 'am' => 'መሳሪያዎች'],
        'chip_supplements' => ['en' => 'Supplements', 'am' => 'ሳፕሊመንቶች'],
        'coming_soon' => ['en' => 'Coming soon.', 'am' => 'በቅርቡ።'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'programs', 'key' => $key]);
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
        LanguageLine::where('group', 'programs')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
