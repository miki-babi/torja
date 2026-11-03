<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Profile photo dialog: view, replace, and delete actions.
     * Amharic strings are best-effort pending review.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.profile' => [
            'photo_title' => ['en' => 'Profile photo', 'am' => 'የመገለጫ ፎቶ'],
            'photo_replace' => ['en' => 'Replace photo', 'am' => 'ፎቶ ቀይር'],
            'photo_delete' => ['en' => 'Delete photo', 'am' => 'ፎቶ ሰርዝ'],
            'photo_delete_confirm' => ['en' => 'Delete your profile photo?', 'am' => 'የመገለጫ ፎቶዎን ይሰርዛሉ?'],
            'photo_deleted' => ['en' => 'Photo removed', 'am' => 'ፎቶ ተሰርዟል'],
            'photo_upload_failed' => ['en' => 'Could not upload the photo.', 'am' => 'ፎቶ መጫን አልተቻለም።'],
            'photo_delete_failed' => ['en' => 'Could not remove the photo.', 'am' => 'ፎቶ ማስወገድ አልተቻለም።'],
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
