<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the free-Pro referral progress card on the member
     * Ambassador page. Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'ambassador' => [
            'pro_reward_title' => ['en' => 'Free Pro', 'am' => 'ፍሪ Pro'],
            'pro_reward_remaining' => ['en' => ':count more friends unlock free Pro', 'am' => 'ፍሪ Pro ለማግኘት :count ጓደኞች ቀርተዋል'],
            'pro_reward_earned' => ['en' => 'Free Pro unlocked — enjoy!', 'am' => 'ፍሪ Pro ተከፍቷል — ይጠቀሙ!'],
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
