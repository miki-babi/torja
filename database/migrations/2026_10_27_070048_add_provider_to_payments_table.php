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
            $table->string('provider')->default('chapa')->index()->after('user_id');
            $table->string('deposit_id')->nullable()->unique()->after('tx_ref');
            $table->string('idempotency_key')->nullable()->unique()->after('deposit_id');
            $table->renameColumn('chapa_response', 'provider_response');
        });

        // Existing rows were all Chapa attempts; plan-unlock payments become
        // Pro payments under the single-subscription model.
        DB::table('payments')->where('purpose', 'plan_unlock')->update(['purpose' => 'pro']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payments')->where('purpose', 'pro')->update(['purpose' => 'plan_unlock']);

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('provider_response', 'chapa_response');
            $table->dropUnique(['deposit_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'deposit_id', 'idempotency_key']);
        });
    }
};
