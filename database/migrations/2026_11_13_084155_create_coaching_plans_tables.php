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
        Schema::create('trainer_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('kind', 20);
            $table->unsignedInteger('price_etb');
            $table->unsignedInteger('duration_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_plan_id')->constrained('trainer_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('trainer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trainer_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->cascadeOnDelete();
            $table->foreignId('client_plan_id')->nullable()->constrained('client_plans')->nullOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->foreignId('payout_id')->nullable()->constrained('trainer_payouts')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_earnings');
        Schema::dropIfExists('trainer_payouts');
        Schema::dropIfExists('client_plans');
        Schema::dropIfExists('trainer_plans');
    }
};
