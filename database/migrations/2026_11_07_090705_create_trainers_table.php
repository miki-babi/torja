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
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique();
            $table->string('brand_name')->nullable();
            $table->text('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->string('mini_app_name')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('webhook_registered_at')->nullable();
            $table->timestamp('menu_button_set_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trainer_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trainer_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('trainer_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_broadcasts');
        Schema::dropIfExists('trainer_clients');
        Schema::dropIfExists('trainers');
    }
};
