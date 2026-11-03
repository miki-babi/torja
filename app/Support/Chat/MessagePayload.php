<?php

namespace App\Support\Chat;

use App\Models\Message;

/**
 * The single wire shape for a chat message — HTTP responses and
 * broadcast events go through here so the client never has to guess.
 * Viewer-derived flags (`mine`, reactions `user_ids`) stay raw; the
 * client resolves them against its own user id.
 */
class MessagePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Message $message): array
    {
        $message->loadMissing(['sender', 'replyTo.sender', 'attachments', 'reactions']);

        $deleted = $message->trashed();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->username ?: $message->sender->name,
                'avatar' => $message->sender->avatar,
            ],
            'body' => $deleted ? null : $message->body,
            'deleted' => $deleted,
            'edited' => ! $deleted && $message->edited_at !== null,
            'reply_to' => $message->replyTo !== null
                ? [
                    'id' => $message->replyTo->id,
                    'sender_name' => $message->replyTo->sender->username ?: $message->replyTo->sender->name,
                    'snippet' => $message->replyTo->trashed()
                        ? null
                        : mb_strimwidth((string) $message->replyTo->body, 0, 80, '…'),
                ]
                : null,
            'attachments' => $deleted
                ? []
                : $message->attachments->map(fn ($a): array => [
                    'id' => $a->id,
                    'url' => $a->url,
                    'kind' => $a->kind,
                    'name' => $a->original_name,
                    'mime' => $a->mime,
                    'size' => $a->size,
                    'width' => $a->width,
                    'height' => $a->height,
                ])->all(),
            'reactions' => $deleted ? [] : self::reactionsFor($message),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Reactions grouped by emoji with the reacting user ids so the client
     * can derive "mine" and display counts.
     *
     * @return array<int, array{emoji: string, count: int, user_ids: array<int>}>
     */
    public static function reactionsFor(Message $message): array
    {
        $message->loadMissing('reactions');

        return $message->reactions
            ->groupBy('emoji')
            ->map(fn ($reactions, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $reactions->count(),
                'user_ids' => $reactions->pluck('user_id')->all(),
            ])
            ->sortBy(fn (array $r): int => -$r['count'])
            ->values()
            ->all();
    }
}
