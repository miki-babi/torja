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
            $table->string('name_am')->nullable()->after('name');
            $table->text('description_am')->nullable()->after('description');
            $table->json('highlights_am')->nullable()->after('highlights');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->dropColumn(['name_am', 'description_am', 'highlights_am']);
        });
    }
};
