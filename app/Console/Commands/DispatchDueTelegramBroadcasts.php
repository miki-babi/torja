<?php

namespace App\Console\Commands;

use App\Jobs\DispatchTelegramBroadcast;
use App\Models\TelegramBroadcast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('broadcasts:dispatch-due')]
#[Description('Queue every scheduled broadcast whose send time has arrived')]
class DispatchDueTelegramBroadcasts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $due = TelegramBroadcast::query()
            ->where('status', 'scheduled')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $broadcast) {
            $trigger = $broadcast->last_run_at === null ? 'schedule' : 'recurrence';

            DispatchTelegramBroadcast::dispatch($broadcast->id, $trigger);
        }

        if ($due->isNotEmpty()) {
            $this->info("Dispatched {$due->count()} broadcast(s).");
        }

        return self::SUCCESS;
    }
}
