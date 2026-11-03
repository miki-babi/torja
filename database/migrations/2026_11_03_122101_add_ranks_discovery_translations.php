<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the friend-suggestion list and the joinable group cards on
     * the Ranks page.
     *
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'find_friends' => ['en' => 'Find friends', 'am' => 'ጓደኞች ፈልግ'],
        'discover_groups' => ['en' => 'Groups to join', 'am' => 'የሚቀላቀሏቸው ቡድኖች'],
        'members' => ['en' => 'members', 'am' => 'አባላት'],
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
