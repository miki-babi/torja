<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Search affordances in the ranks discovery sections. Amharic is
     * best-effort pending review.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.ranks' => [
            'search_groups' => ['en' => 'Search groups…', 'am' => 'ቡድኖችን ይፈልጉ…'],
            'search_no_results' => ['en' => 'No matches.', 'am' => 'ምንም አልተገኘም።'],
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
