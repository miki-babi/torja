<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the /progress exercise progress tracker page, its
     * bottom-nav label, and the home quick chip. Existing locale text is
     * never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'nav' => [
            'progress' => ['en' => 'Progress', 'am' => 'እድገት'],
        ],
        'member.home' => [
            'chip_progress' => ['en' => 'Progress', 'am' => 'እድገት'],
        ],
        'member.progress' => [
            'title' => ['en' => 'Progress', 'am' => 'እድገት'],
            'stat_week' => ['en' => 'sessions this week', 'am' => 'በዚህ ሳምንት ልምምዶች'],
            'stat_total' => ['en' => 'total workouts', 'am' => 'ጠቅላላ ልምምዶች'],
            'stat_volume' => ['en' => 'kg total volume', 'am' => 'ኪግ ጠቅላላ ክብደት'],
            'stat_streak' => ['en' => 'day streak', 'am' => 'ቀን ተከታታይ'],
            'this_week' => ['en' => 'This week', 'am' => 'ይህ ሳምንት'],
            'volume_trend' => ['en' => 'Weekly volume', 'am' => 'ሳምንታዊ ክብደት'],
            'volume_kg' => ['en' => 'Volume (kg)', 'am' => 'ክብደት (ኪግ)'],
            'per_exercise' => ['en' => 'Progress by exercise', 'am' => 'በልምምድ እድገት'],
            'best_weight' => ['en' => 'Best weight', 'am' => 'ከፍተኛ ክብደት'],
            'reps_chart' => ['en' => 'Reps', 'am' => 'ድግግሞሾች'],
            'recent_history' => ['en' => 'Recent', 'am' => 'የቅርብ ጊዜ'],
            'personal_records' => ['en' => 'Personal records', 'am' => 'ግላዊ ሪከርዶች'],
            'pr_empty' => ['en' => 'Log sets to build your record board.', 'am' => 'ስብስቦች ይመዝግቡ የሪከርድ ሰሌዳዎን ለመገንባት።'],
            'history' => ['en' => 'Workout history', 'am' => 'የልምምድ ታሪክ'],
            'free_session' => ['en' => 'Free session', 'am' => 'ነጻ ልምምድ'],
            'exercises_count' => ['en' => ':count exercises', 'am' => ':count ልምምዶች'],
            'sets_count' => ['en' => ':count sets', 'am' => ':count ስብስቦች'],
            'body_metrics' => ['en' => 'Body metrics', 'am' => 'የሰውነት መለኪያዎች'],
            'empty_title' => ['en' => 'No workouts logged yet', 'am' => 'እስካሁን ምንም ልምምድ አልተመዘገበም'],
            'empty_hint' => ['en' => 'Log your first session and your progress charts will appear here.', 'am' => 'የመጀመሪያ ልምምድዎን ይመዝግቡ እና የእድገት ቻርቶችዎ እዚህ ይታያሉ።'],
            'start_workout' => ['en' => 'Start workout', 'am' => 'ልምምድ ይጀምሩ'],
            'browse_exercises' => ['en' => 'Browse exercises', 'am' => 'ልምምዶችን ያስሱ'],
            'log_set' => ['en' => 'Log a set', 'am' => 'ስብስብ ይመዝግቡ'],
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
