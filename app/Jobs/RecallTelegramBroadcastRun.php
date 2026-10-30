<?php

namespace App\Jobs;

use App\Models\TelegramBroadcastDelivery;
use App\Models\TelegramBroadcastRun;
use App\Support\Telegram\TelegramApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalls one sent delivery — deleteMessage for every stored Telegram
 * message id (albums carry several). Failures are ignored: old messages
 * and already-deleted chats just stay put.
 */
class RecallTelegramBroadcastRun implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $deliveryIds
     */
    public function __construct(
        public int $runId,
        public array $deliveryIds,
    ) {}

    /**
     * Delete the stored messages for each delivery in the slice.
     */
    public function handle(TelegramApi $api): void
    {
        $run = TelegramBroadcastRun::query()->find($this->runId);

        if ($run === null) {
            return;
        }

        $deleted = 0;

        TelegramBroadcastDelivery::query()
            ->whereIn('id', $this->deliveryIds)
            ->where('status', 'sent')
            ->whereNull('deleted_at')
            ->whereNotNull('message_ids')
            ->get()
            ->each(function (TelegramBroadcastDelivery $delivery) use ($api, &$deleted): void {
                foreach ($delivery->message_ids ?? [] as $messageId) {
                    $api->deleteMessage($delivery->chat_id, (int) $messageId);
                }

                $delivery->update(['deleted_at' => now()]);
                $deleted++;
            });

        if ($deleted > 0) {
            $run->increment('deleted_count', $deleted);
        }
    }
}
