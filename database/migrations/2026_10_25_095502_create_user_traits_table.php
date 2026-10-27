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
        Schema::create('user_traits', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_route_name', 100)->nullable();
            $table->string('primary_goal', 20)->nullable();
            $table->json('motivations')->nullable();
            $table->string('plan_slug', 60)->nullable();
            $table->boolean('wants_coach')->default(false);
            $table->decimal('budget_amount_etb', 10, 2)->nullable();
            $table->json('equipment')->nullable();
            $table->unsignedInteger('sessions_7d')->default(0);
            $table->unsignedInteger('sessions_30d')->default(0);
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('playlists_count')->default(0);
            $table->unsignedInteger('shared_playlists_count')->default(0);
            $table->unsignedInteger('share_saves_received')->default(0);
            $table->unsignedInteger('pageviews_7d')->default(0);
            $table->unsignedInteger('pageviews_30d')->default(0);
            $table->json('feature_views_30d')->nullable();
            $table->unsignedInteger('food_logs_30d')->default(0);
            $table->unsignedInteger('water_logs_30d')->default(0);
            $table->unsignedInteger('habit_completions_30d')->default(0);
            $table->unsignedInteger('weight_logs_count')->default(0);
            $table->decimal('latest_weight_kg', 5, 1)->nullable();
            $table->decimal('weight_delta_30d', 5, 1)->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_traits');
    }
};
