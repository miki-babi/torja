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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('source_id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_am')->nullable();
            $table->string('category');
            $table->string('body_part');
            $table->string('equipment');
            $table->string('target');
            $table->string('muscle_group');
            $table->json('secondary_muscles');
            $table->text('instructions');
            $table->json('instruction_steps');
            $table->text('instructions_am')->nullable();
            $table->json('instruction_steps_am')->nullable();
            $table->string('translation_status')->nullable();
            $table->string('media_id');
            $table->string('image_path');
            $table->string('gif_path');
            $table->string('attribution');
            $table->string('exercise_type');
            $table->string('level');
            $table->string('location');
            $table->string('movement_pattern')->nullable();
            $table->boolean('compound');
            $table->boolean('isolation');
            $table->boolean('full_body');
            $table->boolean('women_home_friendly');
            $table->boolean('goal_fat_loss');
            $table->boolean('goal_muscle_gain');
            $table->boolean('review_needed');
            $table->json('review_reasons');
            $table->timestamp('source_created_at')->nullable();
            $table->timestamps();

            $table->index(['body_part', 'level']);
            $table->index('equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
