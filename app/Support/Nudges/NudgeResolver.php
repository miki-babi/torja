<?php

namespace App\Support\Nudges;

use App\Models\NutritionProfile;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Support\IntentScorer;
use Illuminate\Http\Request;

/**
 * Picks the single best nudge to show on this request — checked live
 * (profile, pending payment, dismissal log) rather than off `user_traits`,
 * so the banner reflects the state the user is in right now. Nudges are
 * suppressed when the same slug was sent or dismissed inside its cooldown
 * window, and paid users never see conversion nudges.
 */
class NudgeResolver
{
    /**
     * The nudge payload for the Inertia `nudge` shared prop, or null.
     *
     * @return array{slug: string, title: string, body: string, cta: string, url: string}|null
     */
    public function forRequest(Request $request): ?array
    {
        $user = $request->user();

        // Guests only get nudged back into the wizard, which is hidden for
        // now — no guest nudges until the quiz returns.
        if ($user === null) {
            return null;
        }

        if ($user->isPro()) {
            return null;
        }

        $profile = $user->nutritionProfile;
        $goal = $profile?->goal['primary_goal']
            ?? app(IntentScorer::class)->forUser($user)['goal']
            ?? null;

        foreach (NudgeRegistry::slugs() as $slug) {
            if (! $this->eligible($user, $slug, $profile) || $request->routeIs(NudgeRegistry::routeName($slug))) {
                continue;
            }

            if ($this->suppressed($slug, $user->id, null)) {
                continue;
            }

            $stepsLeft = $profile !== null ? max(0, 13 - (int) $profile->current_step) : null;

            return NudgeRegistry::payload($slug, $goal, $stepsLeft, $user->locale ?? null);
        }

        return null;
    }

    /**
     * Whether the user's live state matches the nudge's trigger.
     */
    private function eligible(User $user, string $slug, ?NutritionProfile $profile): bool
    {
        return match ($slug) {
            'checkout_abandoned' => $user->payments()
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subHours(4))
                ->exists(),
            'plan_ready_unpaid' => $profile?->status === 'completed',
            'wizard_abandoned' => $profile !== null
                && $profile->status === 'in_progress'
                && $profile->current_step > 1,
            default => false,
        };
    }

    /**
     * Whether this nudge was recently sent or dismissed for the actor.
     */
    private function suppressed(string $slug, ?int $userId, ?string $visitorId): bool
    {
        return TrackedEvent::query()
            ->whereIn('name', ['nudge.sent', 'nudge.dismissed'])
            ->where('properties->nudge', $slug)
            ->where('occurred_at', '>', now()->subHours(NudgeRegistry::cooldownHours($slug)))
            ->where(function ($query) use ($userId, $visitorId): void {
                if ($userId !== null) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('visitor_id', $visitorId ?? '00000000-0000-0000-0000-000000000000');
                }
            })
            ->exists();
    }
}
