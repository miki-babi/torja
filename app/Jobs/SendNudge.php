<?php

namespace App\Jobs;

use App\Models\TrackedEvent;
use App\Models\User;
use App\Notifications\NudgeNotification;
use App\Support\Nudges\NudgeRegistry;
use App\Support\Tracking\Tracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers one lifecycle nudge to one user. Skips silently when the nudge
 * was already sent (or dismissed) inside its cooldown window, and records
 * `nudge.sent` so dedupe and effectiveness live in the same event log as
 * the funnel.
 */
class SendNudge implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $slug,
    ) {}

    /**
     * Send the nudge and log it.
     */
    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || $user->isPro()) {
            return;
        }

        $recentlySent = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('name', ['nudge.sent', 'nudge.dismissed'])
            ->where('properties->nudge', $this->slug)
            ->where('occurred_at', '>', now()->subHours(NudgeRegistry::cooldownHours($this->slug)))
            ->exists();

        if ($recentlySent) {
            return;
        }

        $user->notify(new NudgeNotification($this->slug));

        app(Tracker::class)->record('nudge.sent', $user, [
            'nudge' => $this->slug,
        ]);
    }
}
