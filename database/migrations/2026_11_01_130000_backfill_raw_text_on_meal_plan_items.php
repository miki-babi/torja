<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Member-facing plan items render their stored raw_text snapshot instead
     * of resolving the food row. Backfill the display name for any legacy
     * items that have a food but no text.
     */
    public function up(): void
    {
        $names = DB::table('foods')->pluck('name_en', 'id');

        DB::table('meal_plan_items')
            ->whereNotNull('food_id')
            ->where(fn ($q) => $q->whereNull('raw_text')->orWhere('raw_text', ''))
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($names): void {
                foreach ($items as $item) {
                    DB::table('meal_plan_items')
                        ->where('id', $item->id)
                        ->update(['raw_text' => $names[$item->food_id] ?? 'Unknown food']);
                }
            });
    }

    public function down(): void
    {
        // Names already rendered — nothing to restore.
    }
};
