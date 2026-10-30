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
        $line = LanguageLine::where('group', 'shop')->where('key', 'place_order')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'en' => 'Order now',
                'am' => 'አሁን እዘዝ',
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
        $line = LanguageLine::where('group', 'shop')->where('key', 'place_order')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'en' => 'Place order — pay on delivery',
                'am' => 'አዝዝ — በማድረስ ጊዜ ይክፈሉ',
            ]);
            $line->save();
        }

        Translator::flush();
    }
};
