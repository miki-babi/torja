<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use App\Support\Telegram\TelegramApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one chat message to one recipient's Telegram DM, when the bot
 * can reach them. Guarded by the room's mute flag and a per-room
 * 15-minute throttle so bursts of messages stay a single notification.
 */
class NotifyChatMessage implements ShouldQueue
{
    use Queueable;

    /**
     * Minimum gap between Telegram pushes for the same room.
     */
    private const THROTTLE_MINUTES = 15;

    public function __construct(
        public int $messageId,
        public int $recipientId,
    ) {}

    /**
     * Send the notification unless the recipient muted the room, was
     * pushed too recently, already read the message, or has no linked
     * Telegram chat.
     */
    public function handle(): void
    {
        $message = Message::with(['conversation', 'sender'])->find($this->messageId);
        $recipient = User::find($this->recipientId);

        if ($message === null || $recipient === null || $message->trashed()) {
            return;
        }

        $conversation = $message->conversation;
        $membership = $conversation->membershipFor($recipient);

        if ($membership === null || $membership->muted_at !== null) {
            return;
        }

        // Members only hear about their coach's thread.
        if (! $recipient->isAdmin() && ! $recipient->isTrainer() && ! $conversation->isCoachThreadFor($recipient)) {
            return;
        }

        if (($membership->last_read_message_id ?? 0) >= $message->id) {
            return;
        }

        if ($membership->last_notified_at !== null
            && $membership->last_notified_at->gt(now()->subMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        $chatId = $recipient->telegramRecipientId();

        if ($chatId === null) {
            return;
        }

        $sender = $message->sender->username ?: $message->sender->name;
        $count = $message->attachments()->count();
        $snippet = $message->body !== null && $message->body !== ''
            ? mb_strimwidth($message->body, 0, 120, '…')
            : "📎 {$count} attachment".($count === 1 ? '' : 's');

        $text = '<b>'.e($sender).'</b>'.":\n".e($snippet);

        $result = app(TelegramApi::class)->sendMessage($chatId, $text);

        if ($result->ok) {
            $membership->forceFill(['last_notified_at' => now()])->save();
        }
    }
}
