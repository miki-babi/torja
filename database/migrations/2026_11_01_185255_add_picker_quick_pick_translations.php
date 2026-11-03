<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Quick-pick row labels for the exercise picker.
     *
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'recent' => ['en' => 'Recent', 'am' => 'የቅርብ ጊዜ'],
        'popular' => ['en' => 'Popular', 'am' => 'ታዋቂ'],
    ];

    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew([
                'group' => 'member.progress',
                'key' => $key,
            ]);
            $line->text = array_merge($text, $line->text ?? []);
            $line->save();
        }

        Translator::flush();
    }

    public function down(): void
    {
        LanguageLine::where('group', 'member.progress')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
