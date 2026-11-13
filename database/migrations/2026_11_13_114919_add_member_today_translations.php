<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Strings for the coach-centric "Today's workout" home screen.
     *
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'title' => ['en' => "Today's workout", 'am' => 'የዛሬ ልምምድ'],
        'hello' => ['en' => 'Hello', 'am' => 'ሰላም'],
        'your_coach' => ['en' => 'Your coach', 'am' => 'አሰልጣኝህ'],
        'no_program' => ['en' => 'No workout program yet', 'am' => 'ገና የልምምድ ፕሮግራም የለም'],
        'no_program_hint' => [
            'en' => 'Your coach will assign one — or open a shared program link they sent you.',
            'am' => 'አሰልጣኝህ ይመድብልሃል — ወይም የላኩልህን የጋራ ፕሮግራም ሊንክ ክፈት።',
        ],
        'workouts' => ['en' => 'workouts', 'am' => 'ልምምዶች'],
        'start' => ['en' => 'Start', 'am' => 'ጀምር'],
        'restart' => ['en' => 'Restart', 'am' => 'እንደገና ጀምር'],
        'this_week' => ['en' => 'This week', 'am' => 'በዚህ ሳምንት'],
        'exercises' => ['en' => 'exercises', 'am' => 'እንቅስቃሴዎች'],
        'checkin_due' => ['en' => 'Weekly check-in is due', 'am' => 'የሳምንታዊ ቼክ-ኢን ጊዜ ደርሷል'],
        'checkin_hint' => [
            'en' => 'Send your weight and a quick update to your coach.',
            'am' => 'ክብደትህን እና አጭር ዝመና ለአሰልጣኝህ ላክ።',
        ],
        'week_stats' => ['en' => ':count session(s) this week', 'am' => 'በዚህ ሳምንት :count ክፍለ ጊዜዎች'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'member.today', 'key' => $key]);
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
        LanguageLine::where('group', 'member.today')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
