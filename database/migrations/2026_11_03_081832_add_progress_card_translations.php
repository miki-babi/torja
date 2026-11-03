<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * CTA label for the progress hero card on member home.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member' => [
            'home.view_progress' => ['en' => 'View progress', 'am' => 'እድገትዎን ይመልከቱ'],
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
