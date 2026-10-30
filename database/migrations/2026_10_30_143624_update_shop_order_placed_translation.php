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
        $line = LanguageLine::where('group', 'shop')->where('key', 'order_placed')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ትዕዛዙ ደርሶናል',
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
        $line = LanguageLine::where('group', 'shop')->where('key', 'order_placed')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ትዕዛዝ ተመዝግቧል — በማድረስ ጊዜ ይክፈሉ።',
            ]);
            $line->save();
        }

        Translator::flush();
    }
};
