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
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->string('exercise_id');
            $table->unsignedSmallInteger('set_index');
            $table->decimal('weight_kg', 6, 1)->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->timestamps();

            $table->unique(['workout_session_id', 'exercise_id', 'set_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
