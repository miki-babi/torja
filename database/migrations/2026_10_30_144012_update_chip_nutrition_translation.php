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
        $line = LanguageLine::where('group', 'programs')->where('key', 'chip_nutrition')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ሚል ፕላን',
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
        $line = LanguageLine::where('group', 'programs')->where('key', 'chip_nutrition')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ካብ',
            ]);
            $line->save();
        }

        Translator::flush();
    }
};
