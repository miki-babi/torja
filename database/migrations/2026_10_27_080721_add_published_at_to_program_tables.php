<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('share_token');
        });

        Schema::table('meal_programs', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('share_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        Schema::table('meal_programs', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
