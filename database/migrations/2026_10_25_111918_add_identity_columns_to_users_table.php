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
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone', 32)->nullable()->unique()->after('username');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('telegram_link_token');
            $table->string('telegram_username')->nullable()->unique()->after('telegram_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropUnique(['telegram_username']);
            $table->dropUnique(['telegram_id']);
            $table->dropUnique(['phone']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'phone',
                'phone_verified_at',
                'telegram_id',
                'telegram_username',
            ]);
        });
    }
};
