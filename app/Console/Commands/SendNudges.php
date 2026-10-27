<?php

namespace App\Console\Commands;

use App\Jobs\SendNudge;
use App\Support\Nudges\NudgeRegistry;
use App\Support\Segments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nudges:send')]
#[Description('Queue lifecycle nudges for users matching each nudge segment')]
class SendNudges extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queued = 0;

        foreach (NudgeRegistry::slugs() as $slug) {
            $count = 0;

            Segments::query($slug)
                ->pluck('user_id')
                ->each(function (int $userId) use ($slug, &$count): void {
                    SendNudge::dispatch($userId, $slug);
                    $count++;
                });

            $this->info("{$slug}: {$count} queued");
            $queued += $count;
        }

        $this->info("{$queued} nudges queued.");

        return self::SUCCESS;
    }
}
