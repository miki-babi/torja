<?php

namespace App\Jobs;

use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\UserTrait;
use App\Services\Nutrition\PlanMatcher;
use App\Support\ChannelNormalizer;
use App\Support\IntentScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds one user's materialized trait row from their nutrition profile,
 * tracked events and domain tables. Runs nightly for all users and ad-hoc
 * (via `defer`) after signal-rich events like a completed wizard or payment.
 */
class RecomputeUserTraits implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    /**
     * Compute and persist the trait row.
     */
    public function handle(): void
    {
        $user = User::query()->with('nutritionProfile')->find($this->userId);

        if ($user === null) {
            return;
        }

        $profile = $user->nutritionProfile;
        $now = now();

        $eventStats = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->selectRaw('MIN(occurred_at) as first_seen')
            ->selectRaw('MAX(occurred_at) as last_seen')
            ->first();

        $views = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->where('name', 'page.view')
            ->selectRaw('SUM(occurred_at >= ?) as d7', [$now->copy()->subDays(7)])
            ->selectRaw('SUM(occurred_at >= ?) as d30', [$now->copy()->subDays(30)])
            ->first();

        $featureViews = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->where('name', 'page.view')
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('route_name')
            ->pluck('route_name')
            ->map(fn (string $route) => explode('.', $route)[0])
            ->countBy()
            ->all();

        $clientEventCounts = TrackedEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->whereIn('name', ['water.logged'])
            ->selectRaw('name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('name')
            ->pluck('total', 'name')
            ->all();

        $shareSaves = TrackedEvent::query()
            ->where('name', 'shared_playlist.saved')
            ->where('occurred_at', '>=', $now->copy()->subDays(90))
            ->where('properties->owner_id', $user->id)
            ->count();

        $sessionCounts = $user->workoutSessions()
            ->selectRaw('SUM(date >= ?) as d7', [$now->copy()->subDays(7)->toDateString()])
            ->selectRaw('SUM(date >= ?) as d30', [$now->copy()->subDays(30)->toDateString()])
            ->selectRaw('COUNT(*) as total')
            ->first();

        $equipment = DB::table('workout_sets')
            ->join('workout_sessions', 'workout_sets.workout_session_id', '=', 'workout_sessions.id')
            ->join('exercises', 'workout_sets.exercise_id', '=', 'exercises.slug')
            ->where('workout_sessions.user_id', $user->id)
            ->distinct()
            ->pluck('exercises.equipment')
            ->merge(
                DB::table('exercise_playlist_items')
                    ->join('exercise_playlists', 'exercise_playlist_items.exercise_playlist_id', '=', 'exercise_playlists.id')
                    ->join('exercises', 'exercise_playlist_items.exercise_id', '=', 'exercises.slug')
                    ->where('exercise_playlists.user_id', $user->id)
                    ->distinct()
                    ->pluck('exercises.equipment'),
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        $attribution = $user->attribution;
        $referrer = is_array($attribution) ? ($attribution['referrer'] ?? null) : null;
        $referrerHost = is_string($referrer)
            ? strtolower((string) parse_url($referrer, PHP_URL_HOST))
            : null;

        $intent = app(IntentScorer::class)->forUser($user);

        $weightLogs = $user->weightLogs()->orderByDesc('logged_on')->limit(60)->get();
        $latestWeight = $weightLogs->first();
        $reference = $weightLogs->first(
            fn ($log) => $log->logged_on->lt($now->copy()->subDays(30)),
        ) ?? $weightLogs->last();

        UserTrait::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_seen_at' => $eventStats?->first_seen,
                'last_seen_at' => $eventStats?->last_seen,
                'last_route_name' => TrackedEvent::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'page.view')
                    ->whereNotNull('route_name')
                    ->latest('occurred_at')
                    ->value('route_name'),
                'channel' => ChannelNormalizer::channel(is_array($attribution) ? $attribution : null),
                'utm_source' => is_array($attribution) ? ($attribution['utm_source'] ?? null) : null,
                'utm_medium' => is_array($attribution) ? ($attribution['utm_medium'] ?? null) : null,
                'utm_campaign' => is_array($attribution) ? ($attribution['utm_campaign'] ?? null) : null,
                'referrer_host' => $referrerHost,
                'wizard_status' => $profile?->status,
                'wizard_step' => $profile?->current_step,
                'predicted_goal' => $intent !== null && $intent['source'] === IntentScorer::PREDICTED
                    ? $intent['goal']
                    : null,
                'goal_source' => $intent['source'] ?? null,
                'primary_goal' => $profile?->goal['primary_goal']
                    ?? ($intent !== null && $intent['source'] === IntentScorer::DECLARED ? $intent['goal'] : null),
                'motivations' => $profile?->goal['motivations'] ?? null,
                'plan_slug' => $profile !== null && $profile->hasAnswers()
                    ? app(PlanMatcher::class)->slugFor($profile)
                    : null,
                'wants_coach' => in_array('coach_checkins', $profile?->obstacles['support'] ?? [], true),
                'budget_amount_etb' => $profile?->budget['budget_amount_etb'] ?? null,
                'equipment' => $equipment,
                'sessions_7d' => (int) ($sessionCounts?->d7 ?? 0),
                'sessions_30d' => (int) ($sessionCounts?->d30 ?? 0),
                'total_sessions' => (int) ($sessionCounts?->total ?? 0),
                'playlists_count' => $user->exercisePlaylists()->whereNull('generated_for')->count(),
                'shared_playlists_count' => $user->exercisePlaylists()->whereNotNull('share_token')->count(),
                'share_saves_received' => $shareSaves,
                'pageviews_7d' => (int) ($views?->d7 ?? 0),
                'pageviews_30d' => (int) ($views?->d30 ?? 0),
                'feature_views_30d' => $featureViews,
                'food_logs_30d' => $user->foodDiaryEntries()
                    ->where('logged_on', '>=', $now->copy()->subDays(30)->toDateString())
                    ->count(),
                'water_logs_30d' => (int) ($clientEventCounts['water.logged'] ?? 0),
                'habit_completions_30d' => DB::table('habit_logs')
                    ->join('habits', 'habit_logs.habit_id', '=', 'habits.id')
                    ->where('habits.user_id', $user->id)
                    ->where('habit_logs.date', '>=', $now->copy()->subDays(30)->toDateString())
                    ->count(),
                'weight_logs_count' => $user->weightLogs()->count(),
                'latest_weight_kg' => $latestWeight?->weight_kg,
                'weight_delta_30d' => $latestWeight !== null && $reference !== null && $latestWeight->id !== $reference->id
                    ? round((float) $latestWeight->weight_kg - (float) $reference->weight_kg, 1)
                    : null,
                'paid' => $user->isPro(),
                'computed_at' => $now,
            ],
        );
    }
}
