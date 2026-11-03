<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Workout recap composer + feed strings, plus the community section on
     * the member home page. Amharic strings are best-effort pending review.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.recap' => [
            'share_cta' => ['en' => 'Share recap', 'am' => 'ክስለት አጋራ'],
            'composer_title' => ['en' => 'Share workout recap', 'am' => 'የክስለት ማጠቃለያ አጋራ'],
            'photo_add' => ['en' => 'Add a photo', 'am' => 'ፎቶ ያክሉ'],
            'photo_change' => ['en' => 'Change photo', 'am' => 'ፎቶ ይቀይሩ'],
            'caption_placeholder' => ['en' => 'Say something about this workout…', 'am' => 'ስለዚህ ክስለት ምን ይላሉ…'],
            'publish' => ['en' => 'Post recap', 'am' => 'ለጥፍ'],
            'published_toast' => ['en' => 'Recap shared to the feed 🎉', 'am' => 'ሪካፕ ተለጥፏል 🎉'],
            'publish_failed_toast' => ['en' => "Couldn't share the recap — try again.", 'am' => 'ሪካፕ ማጋራት አልተቻለም — እንደገና ይሞክሩ።'],
            'free_session' => ['en' => 'Free session', 'am' => 'ነጻ ክስለት'],
            'duration_min' => ['en' => ':count min', 'am' => ':count ደቂቃ'],
            'bravo' => ['en' => 'Bravo', 'am' => 'ብራቮ'],
            'comment_placeholder' => ['en' => 'Write a comment…', 'am' => 'አስተያየት ይጻፉ…'],
            'comment_send' => ['en' => 'Send comment', 'am' => 'አስተያየት ላክ'],
            'comment_failed_toast' => ['en' => "Couldn't post the comment — try again.", 'am' => 'አስተያየት መላክ አልተቻለም — እንደገና ይሞክሩ።'],
            'view_all_comments' => ['en' => 'View all :count comments', 'am' => 'ሁሉንም :count አስተያየቶች ይመልከቱ'],
            'edit_caption' => ['en' => 'Edit caption', 'am' => 'መግለጫ ያርትዑ'],
            'caption_saved' => ['en' => 'Caption updated', 'am' => 'መግለጫ ተዘምኗል'],
            'delete_post' => ['en' => 'Delete post', 'am' => 'ልጥፍ ሰርዝ'],
            'delete_confirm_title' => ['en' => 'Delete this recap?', 'am' => 'ይህን ሪካፕ ይሰርዛሉ?'],
            'delete_confirm_body' => ['en' => 'This removes the post along with its bravos and comments.', 'am' => 'ልጥፉን ከብራቮዎቹ እና አስተያየቶቹ ጋር ያስወግዳል።'],
            'deleted_toast' => ['en' => 'Recap deleted', 'am' => 'ሪካፕ ተሰርዟል'],
            'already_shared' => ['en' => 'Already shared', 'am' => 'አስቀድሞ ተጋርቷል'],
        ],
        'member.home' => [
            'feed_title' => ['en' => 'Community recaps', 'am' => 'የማህበረሰብ ሪካፖች'],
            'feed_empty' => ['en' => 'No recaps yet — finish a workout and be the first to share.', 'am' => 'ገና ሪካፕ የለም — ክስለት ይጨርሱ እና የመጀመሪያውን ያጋሩ።'],
            'feed_load_more' => ['en' => 'Load more', 'am' => 'ተጨማሪ ጫን'],
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
};
