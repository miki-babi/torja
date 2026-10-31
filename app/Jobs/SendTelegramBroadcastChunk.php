<?php

namespace App\Jobs;

use App\Models\TelegramBroadcastDelivery;
use App\Models\TelegramBroadcastRun;
use App\Support\Telegram\BroadcastQueue;
use App\Support\Telegram\BroadcastSender;
use App\Support\Telegram\SendsBroadcasts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sends one slice of a run's deliveries. Already-resolved deliveries are
 * skipped so a 429-triggered retry doesn't double-send; a rate-limited
 * response releases the job for `retry_after` seconds and pending rows
 * get picked up on the next attempt.
 */
class SendTelegramBroadcastChunk implements ShouldQueue
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
     * Send each pending delivery and finalize the run when nothing's left.
     */
    public function handle(BroadcastSender $sender, SendsBroadcasts $runs): void
    {
        Log::info('broadcast.chunk_started', [
            'run_id' => $this->runId,
            'deliveries' => count($this->deliveryIds),
        ]);

        $run = TelegramBroadcastRun::query()->with('broadcast')->find($this->runId);

        if ($run === null || $run->broadcast === null) {
            Log::warning('broadcast.chunk_orphaned', ['run_id' => $this->runId]);

            return;
        }

        $broadcast = $run->broadcast;

        $deliveries = TelegramBroadcastDelivery::query()
            ->whereIn('id', $this->deliveryIds)
            ->get();

        if ($broadcast->status->value === 'cancelled') {
            $deliveries->where('status', 'pending')
                ->each->update(['status' => 'skipped']);

            $runs->finalize($broadcast, $run);

            return;
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->status !== 'pending') {
                continue;
            }

            $delivery->increment('attempts');
            $result = $sender->sendToChat($broadcast, $delivery->chat_id);

            if ($result->ok) {
                $delivery->update([
                    'status' => 'sent',
                    'message_ids' => $result->messageIds,
                    'sent_at' => now(),
                    'error' => null,
                ]);
                $run->increment('sent_count');

                continue;
            }

            if ($result->isRateLimited()) {
                // Requeue the still-pending ids after retry_after instead
                // of release() so the behavior is queue-driver agnostic.
                $remaining = $deliveries
                    ->where('status', 'pending')
                    ->pluck('id')
                    ->all();

                if ($remaining !== []) {
                    BroadcastQueue::dispatch(
                        new self($this->runId, $remaining),
                        max(1, $result->retryAfter ?? 10),
                    );
                }

                Log::info('broadcast.rate_limited', [
                    'run_id' => $this->runId,
                    'remaining' => count($remaining),
                    'retry_after' => $result->retryAfter,
                ]);

                return;
            }

            Log::warning('broadcast.delivery_failed', [
                'delivery_id' => $delivery->id,
                'chat_id' => $delivery->chat_id,
                'error_code' => $result->errorCode,
                'description' => $result->description,
            ]);

            $delivery->update([
                'status' => $result->isBlocked() ? 'blocked' : 'failed',
                'error' => mb_substr((string) $result->description, 0, 250),
            ]);
            $run->increment($result->isBlocked() ? 'blocked_count' : 'failed_count');
        }

        $runs->finalize($broadcast, $run);
    }
}
