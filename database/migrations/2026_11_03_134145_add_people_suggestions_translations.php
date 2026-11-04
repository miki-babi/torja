<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * "People you may know" suggestion reasons — the subtitle under each
     * recommended member explaining the social-graph signal that earned
     * the spot.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.ranks' => [
            'suggest.title' => ['en' => 'People you may know', 'am' => 'ምናልባት የሚያውቋቸው ሰዎች'],
            'suggest.mutual_friends' => ['en' => ':count mutual friends', 'am' => ':count የጋራ ጓደኞች'],
            'suggest.gym' => ['en' => 'Trains at :name', 'am' => 'በ:name ይልምነዳል'],
            'suggest.group' => ['en' => 'In :name', 'am' => 'በ:name ውስጥ'],
            'suggest.nearby' => ['en' => '≈:name km away', 'am' => '≈:name ኪ.ሜ ርቀት'],
            'suggest.new' => ['en' => 'New member', 'am' => 'አዲስ አባል'],
            'suggest.share' => ['en' => 'Share your location to find members & gyms near you', 'am' => 'አቅራቢያዎን ሰዎች እና ጂሞች ለማግኘት አካባቢዎን ያጋሩ'],
            'suggest.share_button' => ['en' => 'Share location', 'am' => 'አካባቢ አጋራ'],
            'suggest.share_denied' => ['en' => 'Location access was not granted.', 'am' => 'የአካባቢ ፍቃድ አልተሰጠም።'],
            'gym.distance' => ['en' => '≈:name km', 'am' => '≈:name ኪ.ሜ'],
            'gym.nearby' => ['en' => 'Near you', 'am' => 'አቅራቢያዎ'],
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
