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
        Schema::table('user_traits', function (Blueprint $table) {
            $table->string('channel')->nullable()->index()->after('last_route_name');
            $table->string('utm_source')->nullable()->after('channel');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('referrer_host')->nullable()->after('utm_campaign');
            $table->string('wizard_status')->nullable()->after('referrer_host');
            $table->unsignedTinyInteger('wizard_step')->nullable()->after('wizard_status');
            $table->string('predicted_goal')->nullable()->after('wizard_step');
            $table->string('goal_source')->nullable()->after('predicted_goal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_traits', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn([
                'channel',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'referrer_host',
                'wizard_status',
                'wizard_step',
                'predicted_goal',
                'goal_source',
            ]);
        });
    }
};
