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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_en');
            $table->string('name_am');
            $table->string('category')->index();
            $table->json('allergens');
            $table->string('common_allergy_label')->nullable();
            $table->boolean('show_in_onboarding');
            $table->boolean('is_vegan');
            $table->unsignedSmallInteger('used_by_foods');
            $table->string('added_for')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
