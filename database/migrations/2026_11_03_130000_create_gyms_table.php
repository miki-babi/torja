<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gyms become first-class entities: members pick a gym from a shared
     * list (or create one) instead of typing a free-text name, which also
     * gives each gym a stable identity for its chat room. Existing
     * `gym_name` values are folded into gym rows — matching the
     * case-insensitive leaderboard grouping — and the column is dropped.
     */
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('name_key', 60)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('gym_id')->nullable()->after('gym_name')->constrained('gyms')->nullOnDelete();
        });

        $now = now();
        $gymIds = [];

        DB::table('users')
            ->whereNotNull('gym_name')
            ->where('gym_name', '!=', '')
            ->orderBy('id')
            ->get(['id', 'gym_name'])
            ->each(function ($user) use (&$gymIds, $now): void {
                $key = mb_strtolower(trim((string) $user->gym_name));

                if ($key === '') {
                    return;
                }

                if (! array_key_exists($key, $gymIds)) {
                    $gymIds[$key] = DB::table('gyms')->insertGetId([
                        'name' => trim((string) $user->gym_name),
                        'name_key' => $key,
                        'created_by' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('users')->where('id', $user->id)->update(['gym_id' => $gymIds[$key]]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gym_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gym_name', 60)->nullable()->after('leaderboard_opted_in');
        });

        DB::table('users')
            ->whereNotNull('gym_id')
            ->join('gyms', 'gyms.id', '=', 'users.gym_id')
            ->update(['users.gym_name' => DB::raw('gyms.name')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gym_id');
        });

        Schema::dropIfExists('gyms');
    }
};
