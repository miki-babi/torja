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
        Schema::create('food_diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->string('name');
            $table->string('detail')->nullable();
            $table->decimal('kcal', 8, 1)->nullable();
            $table->decimal('protein_g', 8, 1)->nullable();
            $table->decimal('carbs_g', 8, 1)->nullable();
            $table->decimal('fat_g', 8, 1)->nullable();
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('source', 20)->default('search');
            $table->boolean('had_photos')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'logged_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_diary_entries');
    }
};
