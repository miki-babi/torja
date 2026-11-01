<?php

namespace App\Jobs;

use App\Models\TelegramBroadcastDelivery;
use App\Models\TelegramBroadcastRun;
use App\Support\Telegram\BroadcastSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Applies a content edit to already-delivered messages — the matching
 * editMessage* call for every stored Telegram message id. Failures are
 * logged and skipped: deleted chats and expired edit windows just keep
 * the old content.
 */
class EditTelegramBroadcastRun implements ShouldQueue
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
     * Edit the stored messages for each delivery in the slice.
     */
    public function handle(BroadcastSender $sender): void
    {
        $run = TelegramBroadcastRun::query()->with('broadcast')->find($this->runId);

        if ($run === null || $run->broadcast === null) {
            Log::warning('broadcast.edit_orphaned', ['run_id' => $this->runId]);

            return;
        }

        $broadcast = $run->broadcast;

        Log::info('broadcast.edit_chunk_started', [
            'run_id' => $this->runId,
            'deliveries' => count($this->deliveryIds),
        ]);

        TelegramBroadcastDelivery::query()
            ->whereIn('id', $this->deliveryIds)
            ->where('status', 'sent')
            ->whereNull('deleted_at')
            ->whereNotNull('message_ids')
            ->get()
            ->each(function (TelegramBroadcastDelivery $delivery) use ($sender, $broadcast): void {
                foreach ($delivery->message_ids ?? [] as $messageId) {
                    $sender->editInChat($broadcast, $delivery->chat_id, (int) $messageId);
                }

                $delivery->update(['edited_at' => now()]);
            });
    }
}
