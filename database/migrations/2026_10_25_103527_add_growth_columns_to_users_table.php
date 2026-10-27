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
        Schema::table('users', function (Blueprint $table) {
            $table->json('attribution')->nullable()->after('locale');
            $table->string('telegram_chat_id')->nullable()->after('attribution');
            $table->string('telegram_link_token')->nullable()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['attribution', 'telegram_chat_id', 'telegram_link_token']);
        });
    }
};
