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
        Schema::create('meal_program_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day');
            $table->string('meal_type');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['meal_program_id', 'day', 'meal_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_program_meals');
    }
};
