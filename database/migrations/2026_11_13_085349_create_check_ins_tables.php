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
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_of');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->text('trainer_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_of']);
            $table->index('created_at');
        });

        Schema::create('check_in_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_in_id')->constrained('check_ins')->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_in_media');
        Schema::dropIfExists('check_ins');
    }
};
