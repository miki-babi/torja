<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('jami')->index()->after('user_id');
            $table->string('deposit_id')->nullable()->unique()->after('tx_ref');
            $table->string('idempotency_key')->nullable()->unique()->after('deposit_id');
        });

        // Plan-unlock payments become Pro payments under the
        // single-subscription model.
        DB::table('payments')->where('purpose', 'plan_unlock')->update(['purpose' => 'pro']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payments')->where('purpose', 'pro')->update(['purpose' => 'plan_unlock']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['deposit_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'deposit_id', 'idempotency_key']);
        });
    }
};
