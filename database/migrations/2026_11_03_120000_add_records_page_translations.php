<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the /progress/records personal records board and the "view
     * all" link on the progress page preview. Existing locale text is never
     * overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.records' => [
            'title' => ['en' => 'Personal records', 'am' => 'ግላዊ ሪከርዶች'],
            'search' => ['en' => 'Search exercises', 'am' => 'ልምምዶችን ይፈልጉ'],
            'all' => ['en' => 'All', 'am' => 'ሁሉም'],
            'empty' => ['en' => 'Log sets to build your record board.', 'am' => 'ስብስቦች ይመዝግቡ የሪከርድ ሰሌዳዎን ለመገንባት።'],
            'no_matches' => ['en' => 'No records match your search.', 'am' => 'ምንም ሪከርዶች ከፍለጋዎ ጋር አይዛመዱም።'],
            'view_all' => ['en' => 'View all :count', 'am' => 'ሁሉንም :count ይመልከቱ'],
            'achieved' => ['en' => 'Achieved', 'am' => 'የተመዘገበ'],
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
