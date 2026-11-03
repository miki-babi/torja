<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * CTA on the home progress card now points at logging instead of
     * viewing.
     *
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'log_workout' => ['en' => 'Log workout', 'am' => 'ልምምድ ይመዝግቡ'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'member.home', 'key' => $key]);
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
        LanguageLine::where('group', 'member.home')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
