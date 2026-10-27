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
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('efct_code', 16)->nullable()->unique();
            $table->string('name_en');
            $table->string('name_am')->nullable();
            $table->string('category')->index();
            $table->decimal('kcal_per_100g', 6, 1)->nullable();
            $table->decimal('protein_g', 5, 1)->nullable();
            $table->decimal('carbs_g', 5, 1)->nullable();
            $table->decimal('fat_g', 5, 1)->nullable();
            $table->decimal('fibre_g', 5, 1)->nullable();
            $table->json('allergens');
            $table->json('meal_types');
            $table->boolean('is_fasting_friendly');
            $table->boolean('fish_ok_fasting');
            $table->boolean('is_vegetarian');
            $table->boolean('is_vegan');
            $table->boolean('contains_meat');
            $table->boolean('goal_fat_loss')->index();
            $table->boolean('goal_muscle_gain')->index();
            $table->text('goal_tag_basis');
            $table->json('used_in_plans');
            $table->string('source');
            $table->boolean('review_needed')->default(false);
            $table->text('data_fix_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
