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
        'order' => ['en' => 'Order', 'am' => 'አዝዝ'],
        'order_placed' => ['en' => 'Order placed — pay on delivery.', 'am' => 'ትዕዛዝ ተመዝግቧል — በማድረስ ጊዜ ይክፈሉ።'],
        'order_failed' => ['en' => 'Could not place the order.', 'am' => 'ትዕዛዝ ማስተጠበቅ አልተቻለም።'],
        'quantity' => ['en' => 'Quantity', 'am' => 'ብዛት'],
        'phone' => ['en' => 'Phone number', 'am' => 'ስልክ ቁጥር'],
        'cod_hint' => ['en' => 'We will call this number to deliver. Pay in cash on delivery.', 'am' => 'ለማድረስ በዚህ ቁጥር እናገኝዎታለን። ክፍያ በማድረስ ጊዜ በጥሬ ገንዘብ።'],
        'total' => ['en' => 'Total', 'am' => 'ጠቅላላ'],
        'place_order' => ['en' => 'Place order — pay on delivery', 'am' => 'አዝዝ — በማድረስ ጊዜ ይክፈሉ'],
        'my_orders' => ['en' => 'My orders', 'am' => 'ትዕዛዦቼ'],
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
