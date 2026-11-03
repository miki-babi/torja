<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat schema: conversations of three types — `dm` (canonical pair
     * key), `group` (one per squad) and `gym` (one per gym). Participants
     * track read state, mute and notification throttling on
     * `conversation_user`. Existing squads and gyms are backfilled with a
     * room and their current members.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 8);
            $table->string('dm_key', 32)->nullable()->unique();
            $table->foreignId('group_id')->nullable()->unique()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->unique()->constrained('gyms')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 16)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('kind', 8);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        $now = now();

        DB::table('groups')->orderBy('id')->get(['id'])->each(function ($group) use ($now): void {
            $conversationId = DB::table('conversations')->insertGetId([
                'type' => 'group',
                'group_id' => $group->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $members = DB::table('group_user')->where('group_id', $group->id)->pluck('user_id');

            DB::table('conversation_user')->insert(
                $members->map(fn ($userId): array => [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });

        DB::table('gyms')->orderBy('id')->get(['id'])->each(function ($gym) use ($now): void {
            $conversationId = DB::table('conversations')->insertGetId([
                'type' => 'gym',
                'gym_id' => $gym->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $members = DB::table('users')->where('gym_id', $gym->id)->pluck('id');

            DB::table('conversation_user')->insert(
                $members->map(fn ($userId): array => [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
