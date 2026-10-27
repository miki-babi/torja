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
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('slug')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_program_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('goal')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('meal_program_id');
            $table->string('goal')->nullable(false)->change();
        });
    }
};
