<?php

namespace App\Support\Telegram;

use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRun;

/**
 * Shared run-completion logic for broadcast jobs. A run is finished once
 * no pending deliveries remain; the broadcast then either advances to
 * its next recurrence slot or settles into `sent`/`cancelled`.
 */
class SendsBroadcasts
{
    /**
     * Close out the run if its deliveries are all resolved, and move the
     * broadcast to its next state.
     */
    public function finalize(TelegramBroadcast $broadcast, TelegramBroadcastRun $run): void
    {
        $pending = $run->deliveries()->where('status', 'pending')->exists();

        if ($pending) {
            return;
        }

        if ($run->finished_at === null) {
            $run->update(['finished_at' => now()]);
        }

        if ($broadcast->status->value === 'cancelled') {
            return;
        }

        $nextRun = $broadcast->isRecurring()
            ? $broadcast->nextRunAfter($broadcast->next_run_at ?? now())
            : null;

        $broadcast->forceFill([
            'status' => $nextRun !== null ? 'scheduled' : 'sent',
            'next_run_at' => $nextRun,
        ])->save();
    }
}
