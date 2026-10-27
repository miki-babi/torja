<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeUserTraits;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('traits:recompute {--user= : Recompute a single user id}')]
#[Description('Recompute materialized user traits for segmentation')]
class RecomputeUserTraitsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        $query = User::query()->select('id');

        if ($userId !== null) {
            $query->whereKey((int) $userId);
        }

        $count = 0;

        $query->chunkById(200, function ($users) use (&$count): void {
            foreach ($users as $user) {
                RecomputeUserTraits::dispatchSync($user->id);
                $count++;
            }
        });

        $this->info("Recomputed traits for {$count} user(s).");

        return self::SUCCESS;
    }
}
