<?php

namespace App\Jobs;

use App\Models\TrainerBroadcast;
use App\Support\Telegram\TelegramApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans a trainer broadcast out to every linked client reachable on
 * Telegram, through the trainer's own bot. Sends are throttled to stay
 * under Bot API rate limits; a 429 releases the job for `retry_after`.
 */
class SendTrainerBroadcast implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $broadcastId,
    ) {}

    /**
     * Send the body to each client, tallying sent/failed counts on the row.
     */
    public function handle(): void
    {
        $broadcast = TrainerBroadcast::query()
            ->with('trainer.clients')
            ->find($this->broadcastId);

        $trainer = $broadcast?->trainer;

        if ($broadcast === null || $trainer === null || ! $trainer->hasConnectedBot()) {
            return;
        }

        $api = TelegramApi::forToken($trainer->bot_token);
        $body = e($broadcast->body);

        foreach ($trainer->clients as $client) {
            $chatId = $client->telegramRecipientId();

            if ($chatId === null) {
                $broadcast->increment('failed_count');

                continue;
            }

            $result = $api->sendMessage($chatId, $body);

            if (! $result->ok && $result->retryAfter !== null) {
                $this->release($result->retryAfter);

                return;
            }

            $broadcast->increment($result->ok ? 'sent_count' : 'failed_count');

            // ~20 messages/second keeps the fan-out under Bot API limits.
            usleep(50_000);
        }
    }
}
