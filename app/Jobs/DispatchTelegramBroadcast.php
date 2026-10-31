<?php

namespace App\Jobs;

use App\Models\TelegramBroadcast;
use App\Support\Telegram\BroadcastAudience;
use App\Support\Telegram\BroadcastQueue;
use App\Support\Telegram\SendsBroadcasts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Kicks off one run of a broadcast: resolves the audience, creates the
 * run + pending delivery rows, then fans out staggered send chunks.
 * Telegram's ~30 messages/second ceiling maps to 25 deliveries per chunk
 * dispatched one second apart.
 */
class DispatchTelegramBroadcast implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 25;

    public function __construct(
        public int $broadcastId,
        public string $trigger = 'manual',
    ) {}

    /**
     * Build the run and queue the send chunks.
     */
    public function handle(): void
    {
        Log::info('broadcast.dispatch_started', [
            'broadcast_id' => $this->broadcastId,
            'trigger' => $this->trigger,
        ]);

        try {
            $this->dispatchRun();
        } catch (\Throwable $e) {
            Log::error('broadcast.dispatch_failed', [
                'broadcast_id' => $this->broadcastId,
                'trigger' => $this->trigger,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function dispatchRun(): void
    {
        $broadcast = TelegramBroadcast::query()->find($this->broadcastId);

        if ($broadcast === null || $broadcast->status->value === 'cancelled') {
            return;
        }

        $broadcast->forceFill([
            'status' => 'sending',
            'last_run_at' => now(),
        ])->save();

        $run = $broadcast->runs()->create([
            'trigger' => $this->trigger,
            'started_at' => now(),
        ]);

        $chunkIndex = 0;
        $recipients = 0;

        BroadcastAudience::query($broadcast->audience ?? [])
            ->select('id', 'telegram_chat_id', 'telegram_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use ($broadcast, $run, &$chunkIndex, &$recipients): void {
                $deliveryIds = [];

                foreach ($users as $user) {
                    $deliveryIds[] = $run->deliveries()->create([
                        'broadcast_id' => $broadcast->id,
                        'user_id' => $user->id,
                        'chat_id' => $user->telegramRecipientId(),
                        'status' => 'pending',
                    ])->id;
                    $recipients++;
                }

                foreach (array_chunk($deliveryIds, self::CHUNK_SIZE) as $ids) {
                    BroadcastQueue::dispatch(
                        new SendTelegramBroadcastChunk($run->id, $ids),
                        $chunkIndex++,
                    );
                }
            });

        $run->update(['recipients_count' => $recipients]);

        Log::info('broadcast.audience_resolved', [
            'broadcast_id' => $broadcast->id,
            'run_id' => $run->id,
            'recipients' => $recipients,
            'chunks' => $chunkIndex,
        ]);

        // Nobody to send to — finish immediately rather than leaving the
        // broadcast stuck in `sending`.
        if ($recipients === 0) {
            Log::warning('broadcast.no_recipients', [
                'broadcast_id' => $broadcast->id,
                'run_id' => $run->id,
            ]);

            $run->update(['finished_at' => now()]);
            app(SendsBroadcasts::class)->finalize($broadcast, $run);
        }
    }
}
