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
        Schema::create('tracking_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('name');
            $table->string('external_id');
            $table->json('config')->nullable();
            $table->json('scopes');
            $table->json('event_map')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('consent_required')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_integrations');
    }
};
