<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Location-share recovery strings — the toast shown when the member
     * declines Telegram's location prompt ("Open settings") and when the
     * device cannot supply a location at all.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.ranks' => [
            'suggest.share_settings' => ['en' => 'Open settings', 'am' => 'ቅንብሮችን ክፈት'],
            'suggest.share_unavailable' => ['en' => "Location isn't available on this device.", 'am' => 'በዚህ መሣሪያ ላይ አካባቢ አይገኝም።'],
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
