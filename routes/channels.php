<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel per conversation — the returned payload doubles as the
 * member's presence entry for the online roster.
 *
 * @return array{id: int, name: string, avatar: string|null}|false
 */
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation): array|false {
    if (! $conversation->isParticipant($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->username ?: $user->name,
        'avatar' => $user->avatar,
    ];
});

/**
 * Per-user private channel for inbox updates (new activity, unread bumps).
 */
Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
