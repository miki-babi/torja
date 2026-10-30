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
        $line = LanguageLine::where('group', 'shop')->where('key', 'cod_hint')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ትዕዛዝዎን ለማረጋገጥ፣ ባስገቡት ስልክ ቁጥር አማካኝነት እንደውልልዎታለን።',
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
        $line = LanguageLine::where('group', 'shop')->where('key', 'cod_hint')->first();

        if ($line !== null) {
            $line->text = array_merge($line->text ?? [], [
                'am' => 'ለማድረስ በዚህ ቁጥር እናገኝዎታለን። ክፍያ በማድረስ ጊዜ በጥሬ ገንዘብ።',
            ]);
            $line->save();
        }

        Translator::flush();
    }
};
