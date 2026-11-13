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
        DB::table('users')->whereIn('role', ['user', 'member'])->update(['role' => 'client']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'client')->update(['role' => 'user']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->change();
        });
    }
};
