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
        Schema::create('nutrition_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('status')->default('in_progress');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->json('goal')->nullable();
            $table->json('body')->nullable();
            $table->json('activity')->nullable();
            $table->json('pace')->nullable();
            $table->json('diet')->nullable();
            $table->json('liked_foods')->nullable();
            $table->json('avoidances')->nullable();
            $table->json('habits')->nullable();
            $table->json('schedule')->nullable();
            $table->json('budget')->nullable();
            $table->json('cooking')->nullable();
            $table->json('obstacles')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutrition_profiles');
    }
};
