<?php

namespace App\Console\Commands;

use App\Models\TrackedEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tracking:prune {--months=12 : Delete events older than this many months}')]
#[Description('Delete tracked events older than the retention window')]
class PruneTrackedEvents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months);

        $deleted = TrackedEvent::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} tracked events older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
