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
        Schema::table('trainers', function (Blueprint $table) {
            $table->string('lead_form_mode', 20)->default('required')->after('offer_text');
        });

        Schema::create('trainer_lead_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_lead_fields');

        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn('lead_form_mode');
        });
    }
};
