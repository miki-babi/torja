<?php

namespace App\Console\Commands;

use App\Models\CheckIn;
use App\Models\ClientPlan;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Trainer\ClientNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('reminders:send')]
#[Description('Send Telegram reminders to trainer clients: missed workout, missing check-in, expiring plan')]
class SendClientReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ClientNotifier $notifier): int
    {
        $sent = 0;

        Trainer::query()
            ->whereNotNull('bot_token')
            ->with(['clients' => fn ($query) => $query->where('reminders_enabled', true)])
            ->chunkById(50, function ($trainers) use ($notifier, &$sent): void {
                foreach ($trainers as $trainer) {
                    foreach ($trainer->clients as $client) {
                        $sent += $this->remind($notifier, $trainer, $client);
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Send every reminder type this client is due for right now.
     */
    private function remind(ClientNotifier $notifier, Trainer $trainer, User $client): int
    {
        $sent = 0;
        $coach = e($trainer->display_name);
        $name = e($client->name);

        if ($this->workoutDue($client)) {
            $notifier->notify(
                $trainer,
                $client,
                "🏋️ Hey {$name}, it's been 3 days since your last workout — {$coach} is checking in. Time to get a session in!",
            );
            $sent++;
        }

        if ($this->checkInDue($client)) {
            $notifier->notify(
                $trainer,
                $client,
                "📋 Hey {$name}, the week is wrapping up — send {$coach} your weekly check-in before it ends.",
            );
            $sent++;
        }

        foreach ($this->expiringPlans($trainer, $client) as $daysLeft) {
            $when = $daysLeft <= 1 ? 'tomorrow' : "in {$daysLeft} days";
            $notifier->notify(
                $trainer,
                $client,
                "⏳ Hey {$name}, your coaching plan with {$coach} ends {$when}. Renew to keep the momentum going.",
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Last workout was exactly 3 days ago — one nudge per lapse, so a
     * permanently idle member isn't spammed daily.
     */
    private function workoutDue(User $client): bool
    {
        $last = $client->workoutSessions()->max('date');

        return $last !== null
            && Carbon::parse($last)->isSameDay(now()->subDays(3));
    }

    /**
     * Sunday only: the check-in week closes tonight and they haven't sent
     * theirs.
     */
    private function checkInDue(User $client): bool
    {
        if (! now()->isSunday()) {
            return false;
        }

        return ! $client->checkIns()
            ->whereDate('week_of', CheckIn::currentWeek())
            ->exists();
    }

    /**
     * Days-left values for each active subscription on this trainer ending
     * exactly tomorrow or in 3 days — two escalating pings per expiry.
     *
     * @return list<int>
     */
    private function expiringPlans(Trainer $trainer, User $client): array
    {
        return ClientPlan::query()
            ->where('user_id', $client->id)
            ->where('status', 'active')
            ->whereHas('plan', fn ($query) => $query->where('trainer_id', $trainer->id))
            ->where(function ($dates) {
                $dates->whereDate('ends_at', now()->addDay())
                    ->orWhereDate('ends_at', now()->addDays(3));
            })
            ->pluck('ends_at')
            ->map(fn ($endsAt) => (int) round(now()->diffInDays($endsAt)))
            ->all();
    }
}
