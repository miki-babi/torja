<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<string, array<string, string>>
     */
    private const LINES = [
        'login_to_order' => ['en' => 'Log in to order', 'am' => 'ለማዘዝ ይግቡ'],
    ];

    public function up(): void
    {
        foreach (self::LINES as $key => $text) {
            $line = LanguageLine::firstOrNew(['group' => 'shop', 'key' => $key]);
            $line->text = array_merge($text, $line->text ?? []);
            $line->save();
        }

        Translator::flush();
    }

    public function down(): void
    {
        LanguageLine::where('group', 'shop')
            ->whereIn('key', array_keys(self::LINES))
            ->delete();

        Translator::flush();
    }
};
