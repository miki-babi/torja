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
        Schema::table('exercise_playlists', function (Blueprint $table) {
            $table->date('generated_for')->nullable()->after('name');
            $table->index(['user_id', 'generated_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_playlists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'generated_for']);
            $table->dropColumn('generated_for');
        });
    }
};
