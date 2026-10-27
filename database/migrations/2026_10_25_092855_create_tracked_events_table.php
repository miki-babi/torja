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
        Schema::create('tracked_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uid')->unique();
            $table->uuid('visitor_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('name', 60);
            $table->string('route_name', 100)->nullable();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at', 3);
            $table->timestamp('created_at')->nullable();

            $table->index(['name', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_events');
    }
};
