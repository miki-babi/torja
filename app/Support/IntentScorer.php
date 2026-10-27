<?php

namespace App\Support;

use App\Models\Exercise;
use App\Models\TrackedEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves "what does this actor want?" — declared intent always wins
 * (wizard goal answers, stored profile), then a rule-based prediction from
 * observed behavior: which exercises they viewed (goal flags on the
 * catalog), which page families they browsed, and campaign keywords on
 * their first-touch attribution. Deterministic and explainable — scores
 * are reported as 'predicted' and never overwrite a declared answer.
 */
class IntentScorer
{
    public const DECLARED = 'declared';

    public const PREDICTED = 'predicted';

    /**
     * Resolve a user's intent: declared goal first, then behavioral score.
     *
     * @return array{goal: string, source: string}|null
     */
    public function forUser(User $user): ?array
    {
        $declared = $user->nutritionProfile?->goal['primary_goal'] ?? null;

        if (is_string($declared) && $declared !== '') {
            return ['goal' => $declared, 'source' => self::DECLARED];
        }

        $eventGoal = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('name', ['wizard.step_completed', 'wizard.completed'])
            ->whereNotNull('properties->primary_goal')
            ->latest('occurred_at')
            ->value('properties->primary_goal');

        if (is_string($eventGoal) && $eventGoal !== '') {
            return ['goal' => $eventGoal, 'source' => self::DECLARED];
        }

        return $this->predict(fn ($query) => $query->where('user_id', $user->id));
    }

    /**
     * Predict intent for an anonymous visitor from their tracked events.
     *
     * @return array{goal: string, source: string}|null
     */
    public function forVisitor(string $visitorId): ?array
    {
        $eventGoal = TrackedEvent::query()
            ->where('visitor_id', $visitorId)
            ->whereIn('name', ['wizard.step_completed', 'wizard.completed'])
            ->whereNotNull('properties->primary_goal')
            ->latest('occurred_at')
            ->value('properties->primary_goal');

        if (is_string($eventGoal) && $eventGoal !== '') {
            return ['goal' => $eventGoal, 'source' => self::DECLARED];
        }

        return $this->predict(fn ($query) => $query->where('visitor_id', $visitorId));
    }

    /**
     * Rule-based prediction over the actor's recent events.
     *
     * @return array{goal: string, source: string}|null
     */
    private function predict(callable $scope): ?array
    {
        $events = $scope(TrackedEvent::query())
            ->where('occurred_at', '>=', now()->subDays(90))
            ->whereIn('name', [
                'exercise.viewed',
                'page.view',
                'shared_playlist.viewed',
                'meal.viewed',
            ])
            ->latest('occurred_at')
            ->limit(300)
            ->get(['name', 'route_name', 'subject_type', 'subject_id', 'context']);

        if ($events->isEmpty()) {
            return null;
        }

        $scores = ['lose_fat' => 0, 'build_muscle' => 0];

        $this->scoreExercises($events, $scores);
        $this->scorePages($events, $scores);
        $this->scoreCampaign($events, $scores);

        $winner = array_keys($scores, max($scores), true)[0] ?? null;
        $runnerUp = min($scores) === max($scores) ? null : array_keys($scores, min($scores), true)[0];

        if ($winner === null || $scores[$winner] < 3 || $runnerUp === null || $scores[$winner] - $scores[$runnerUp] < 2) {
            return null;
        }

        return ['goal' => $winner, 'source' => self::PREDICTED];
    }

    /**
     * Viewed exercises carry goal flags on the catalog rows.
     *
     * @param  Collection<int, TrackedEvent>  $events
     * @param  array<string, int>  $scores
     */
    private function scoreExercises(Collection $events, array &$scores): void
    {
        $slugs = $events
            ->filter(fn (TrackedEvent $e): bool => $e->name === 'exercise.viewed'
                && $e->subject_type === (new Exercise)->getMorphClass())
            ->pluck('subject_id')
            ->unique()
            ->all();

        if ($slugs === []) {
            return;
        }

        Exercise::query()
            ->whereIn('slug', $slugs)
            ->get(['goal_fat_loss', 'goal_muscle_gain'])
            ->each(function (Exercise $exercise) use (&$scores): void {
                if ($exercise->goal_muscle_gain) {
                    $scores['build_muscle'] += 2;
                }

                if ($exercise->goal_fat_loss) {
                    $scores['lose_fat'] += 2;
                }
            });
    }

    /**
     * Page families as weak signals: exercise browsing leans muscle,
     * meal/calculator usage leans fat loss.
     *
     * @param  Collection<int, TrackedEvent>  $events
     * @param  array<string, int>  $scores
     */
    private function scorePages(Collection $events, array &$scores): void
    {
        $events
            ->filter(fn (TrackedEvent $e): bool => $e->name === 'page.view' || $e->name === 'meal.viewed')
            ->each(function (TrackedEvent $e) use (&$scores): void {
                $route = $e->route_name ?? '';

                if (str_starts_with($route, 'member.exercise') || str_starts_with($route, 'playlists.shared')) {
                    $scores['build_muscle']++;
                }

                if (str_starts_with($route, 'member.meal') || $route === 'calculator' || $e->name === 'meal.viewed') {
                    $scores['lose_fat']++;
                }
            });
    }

    /**
     * Campaign names on the first-touch attribution carry keyword hints.
     *
     * @param  Collection<int, TrackedEvent>  $events
     * @param  array<string, int>  $scores
     */
    private function scoreCampaign(Collection $events, array &$scores): void
    {
        $attribution = $events
            ->map(fn (TrackedEvent $e) => $e->context['attribution'] ?? null)
            ->first(fn ($attribution) => is_array($attribution));

        if (! is_array($attribution)) {
            return;
        }

        $campaign = strtolower((string) ($attribution['utm_campaign'] ?? '').' '.($attribution['utm_content'] ?? ''));

        if ($campaign === '') {
            return;
        }

        if (preg_match('/lose|loss|weight|fat|slim|cut/', $campaign) === 1) {
            $scores['lose_fat'] += 3;
        }

        if (preg_match('/muscle|gain|bulk|strength|build/', $campaign) === 1) {
            $scores['build_muscle'] += 3;
        }
    }
}
