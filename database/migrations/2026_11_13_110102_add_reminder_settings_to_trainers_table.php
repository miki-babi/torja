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
        Schema::table('trainers', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true)->after('offer_text');
            $table->time('quiet_hours_start')->nullable()->after('reminders_enabled');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'quiet_hours_start', 'quiet_hours_end']);
        });
    }
};
