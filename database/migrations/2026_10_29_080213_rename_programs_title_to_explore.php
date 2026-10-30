<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $line = LanguageLine::where('group', 'programs')->where('key', 'title')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'en' => 'Explore',
                'am' => 'ያስሱ',
            ]);
            $line->save();
        }

        Translator::flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $line = LanguageLine::where('group', 'programs')->where('key', 'title')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'en' => 'Programs',
                'am' => 'ፕሮግራሞች',
            ]);
            $line->save();
        }

        Translator::flush();
    }
};
