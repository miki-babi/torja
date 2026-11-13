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
            $table->text('bio')->nullable()->after('brand_name');
            $table->string('specialty')->nullable()->after('bio');
            $table->json('languages')->nullable()->after('specialty');
            $table->unsignedTinyInteger('years_experience')->nullable()->after('languages');
            $table->string('certification_path')->nullable()->after('years_experience');
            $table->string('payout_method', 20)->nullable()->after('menu_button_set_at');
            $table->string('payout_account')->nullable()->after('payout_method');
            $table->text('offer_text')->nullable()->after('payout_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'specialty',
                'languages',
                'years_experience',
                'certification_path',
                'payout_method',
                'payout_account',
                'offer_text',
            ]);
        });
    }
};
