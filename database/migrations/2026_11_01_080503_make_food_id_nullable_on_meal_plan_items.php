<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom wizard items have no catalog food — the display name lives in
     * raw_text and falls back to it when food_id is null.
     */
    public function up(): void
    {
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->foreignId('food_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->foreignId('food_id')->nullable(false)->change();
        });
    }
};
