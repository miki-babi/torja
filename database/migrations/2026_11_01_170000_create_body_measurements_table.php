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
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('arm_cm', 5, 1)->nullable();
            $table->decimal('hip_cm', 5, 1)->nullable();
            $table->decimal('thigh_cm', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'logged_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
