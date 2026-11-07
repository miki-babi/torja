<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Ranks feature set — leaderboards, friends, squads and gyms —
 * now that the member app is coach-scoped and chat is coach-only. Group and
 * gym rooms lose their backing entities, so they are deleted first; member
 * DM rooms stay in place (members can no longer open non-coach threads).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('conversations')->whereIn('type', ['group', 'gym'])->delete();

        foreach (['group_id', 'gym_id'] as $column) {
            if (Schema::hasColumn('conversations', $column)) {
                Schema::table('conversations', function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
                Schema::table('conversations', function (Blueprint $table) use ($column) {
                    $table->dropUnique([$column]);
                });
                Schema::table('conversations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('users', 'gym_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('gym_id');
            });
        }

        foreach (['leaderboard_opted_in', 'gym_name', 'latitude', 'longitude', 'location_shared_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('friendships');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('gyms');

        DB::table('language_lines')->where('group', 'member.ranks')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Destructive cleanup — no automatic rebuild of the dropped schema.
    }
};
