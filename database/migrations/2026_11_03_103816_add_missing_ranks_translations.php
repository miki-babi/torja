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
        'period.today' => ['en' => 'Today', 'am' => 'ዛሬ'],
        'period.week' => ['en' => 'Week', 'am' => 'ሳምንት'],
        'period.all' => ['en' => 'All time', 'am' => 'ሁሉም'],
        'top_performers' => ['en' => 'Top performers', 'am' => 'ከፍተኞች'],
        'workouts' => ['en' => 'workouts', 'am' => 'ልምምዶች'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'member.ranks', 'key' => $key]);
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
        LanguageLine::where('group', 'member.ranks')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
