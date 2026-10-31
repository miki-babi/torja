<?php

namespace App\Support\Telegram;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Dispatch point for broadcast jobs. Normally everything goes on the
 * queue; when `telegram.broadcast_sync` is enabled the job runs inline
 * instead, so sends work without a queue worker. The sync path covers
 * the whole fan-out — outer dispatch, send chunks, resends and recalls —
 * because every call site goes through here.
 */
class BroadcastQueue
{
    /**
     * Queue the job — or run it inline when sync mode is on. Delays are
     * ignored in sync mode (the job runs immediately).
     */
    public static function dispatch(ShouldQueue $job, int $delaySeconds = 0): void
    {
        if (config('services.telegram.broadcast_sync')) {
            dispatch_sync($job);

            return;
        }

        dispatch($job)->delay(now()->addSeconds($delaySeconds));
    }
}
