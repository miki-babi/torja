<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\UserTrait;
use App\Support\ChannelNormalizer;
use App\Support\Segments;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    /**
     * Product analytics: engagement, feature split, usage hours, wizard
     * funnel, playlist/share stats and computed audience segments.
     */
    public function index(): Response
    {
        $now = now();

        $activeUsers = fn (int $days): int => TrackedEvent::query()
            ->where('occurred_at', '>=', $now->copy()->subDays($days))
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $featureSplit = TrackedEvent::query()
            ->where('name', 'page.view')
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('route_name')
            ->pluck('route_name')
            ->map(fn (string $route) => explode('.', $route)[0])
            ->countBy()
            ->sortDesc()
            ->all();

        $hourGrid = TrackedEvent::query()
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->pluck('occurred_at')
            ->mapWithKeys(fn ($at) => [
                $at->dayOfWeekIso - 1 .':'.$at->hour => 1,
            ])
            ->countBy()
            ->all();

        $topEvents = TrackedEvent::query()
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('name')
            ->selectRaw('COUNT(*) as hits')
            ->groupBy('name')
            ->orderByDesc('hits')
            ->limit(15)
            ->pluck('hits', 'name')
            ->all();

        $wizardSteps = TrackedEvent::query()
            ->where('name', 'wizard.step_completed')
            ->pluck('properties')
            ->map(fn ($props) => is_array($props) ? ($props['section'] ?? null) : null)
            ->filter()
            ->countBy()
            ->all();

        $eventCount = fn (string $name): int => TrackedEvent::query()
            ->where('name', $name)
            ->where('occurred_at', '>=', $now->copy()->subDays(30))
            ->count();

        $segments = collect(Segments::labels())
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => Segments::query($key)->count(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/analytics', [
            'engagement' => [
                'dau' => $activeUsers(1),
                'wau' => $activeUsers(7),
                'mau' => $activeUsers(30),
                'total_events' => TrackedEvent::query()->count(),
                'events_30d' => TrackedEvent::query()
                    ->where('occurred_at', '>=', $now->copy()->subDays(30))
                    ->count(),
            ],
            'featureSplit' => $featureSplit,
            'hourGrid' => $hourGrid,
            'topEvents' => $topEvents,
            'wizardFunnel' => $wizardSteps,
            'social' => [
                'playlists_created' => $eventCount('playlist.created'),
                'playlists_shared' => $eventCount('playlist.shared'),
                'shared_views' => $eventCount('shared_playlist.viewed'),
                'shared_saves' => $eventCount('shared_playlist.saved'),
            ],
            'segments' => $segments,
            'traffic' => $this->traffic(),
        ]);
    }

    /**
     * Per-channel conversion funnel (last 90 days): first-touch visitors →
     * registrations → wizard completions → paying users, plus the goal mix
     * per channel (declared goal, else the predicted one). `unknown` holds
     * visitors whose events predate attribution capture.
     *
     * @return array<int, array{channel: string, visitors: int, registered: int, wizard_done: int, paid: int, conv_pct: float, goals: array<string, int>}>
     */
    private function traffic(): array
    {
        $cutoff = now()->subDays(90);

        $firstTouch = TrackedEvent::query()
            ->where('occurred_at', '>=', $cutoff)
            ->whereNotNull('visitor_id')
            ->where('context', 'like', '%"attribution":{%')
            ->orderBy('occurred_at')
            ->get(['visitor_id', 'context'])
            ->unique('visitor_id')
            ->mapWithKeys(fn (TrackedEvent $event): array => [
                $event->visitor_id => ChannelNormalizer::channel($event->context['attribution'] ?? null),
            ]);

        $visitors = $firstTouch->countBy()->all();

        $totalVisitors = TrackedEvent::query()
            ->where('occurred_at', '>=', $cutoff)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->count('visitor_id');

        $visitors['unknown'] = max(0, $totalVisitors - $firstTouch->count());

        $users = User::query()
            ->where('created_at', '>=', $cutoff)
            ->get(['id', 'attribution']);

        $unattributed = $users->where('attribution', null)->pluck('id');

        $fallback = $unattributed->isEmpty()
            ? collect()
            : TrackedEvent::query()
                ->whereIn('user_id', $unattributed)
                ->where('context', 'like', '%"attribution":{%')
                ->orderBy('occurred_at')
                ->get(['user_id', 'context'])
                ->unique('user_id')
                ->mapWithKeys(fn (TrackedEvent $event): array => [
                    $event->user_id => $event->context['attribution'] ?? null,
                ]);

        $registered = $users
            ->groupBy(fn (User $user): string => ChannelNormalizer::channel(
                is_array($user->attribution) ? $user->attribution : $fallback->get($user->id),
            ))
            ->map->count()
            ->all();

        $traits = UserTrait::query()
            ->get(['channel', 'wizard_status', 'paid', 'primary_goal', 'predicted_goal']);

        $wizardDone = $traits->where('wizard_status', 'completed')->countBy('channel')->all();
        $paidByChannel = $traits->where('paid', true)->countBy('channel')->all();

        $goals = $traits
            ->groupBy('channel')
            ->map(fn ($rows) => $rows
                ->map(fn (UserTrait $t) => $t->primary_goal ?? $t->predicted_goal)
                ->filter()
                ->countBy()
                ->sortDesc()
                ->take(3)
                ->all())
            ->all();

        $channels = collect([
            ...array_keys($visitors),
            ...array_keys($registered),
            ...array_keys($wizardDone),
            ...array_keys($paidByChannel),
        ])->unique()->sort();

        return $channels
            ->map(fn (string $channel): array => [
                'channel' => $channel,
                'visitors' => $visitors[$channel] ?? 0,
                'registered' => $registered[$channel] ?? 0,
                'wizard_done' => $wizardDone[$channel] ?? 0,
                'paid' => $paidByChannel[$channel] ?? 0,
                'conv_pct' => ($visitors[$channel] ?? 0) > 0
                    ? round(($paidByChannel[$channel] ?? 0) / $visitors[$channel] * 100, 1)
                    : 0.0,
                'goals' => $goals[$channel] ?? [],
            ])
            ->sortByDesc('visitors')
            ->values()
            ->all();
    }

    /**
     * One segment's members for inspection.
     */
    public function segment(string $key): Response
    {
        abort_unless(Segments::exists($key), 404);

        return Inertia::render('admin/segment', [
            'segment' => ['key' => $key, 'label' => Segments::labels()[$key]],
            'members' => Segments::query($key)
                ->with('user:id,name,email,phone,username')
                ->limit(200)
                ->get()
                ->map(fn (UserTrait $trait): array => [
                    'id' => $trait->user_id,
                    'name' => $trait->user->name,
                    'email' => $trait->user->email ?? $trait->user->phone ?? $trait->user->username,
                    'last_seen_at' => $trait->last_seen_at?->toDateTimeString(),
                    'sessions_30d' => $trait->sessions_30d,
                    'food_logs_30d' => $trait->food_logs_30d,
                    'paid' => $trait->paid,
                ])
                ->all(),
        ]);
    }

    /**
     * CSV export of one segment for Meta Custom Audiences / outreach.
     */
    public function segmentCsv(string $key): StreamedResponse
    {
        abort_unless(Segments::exists($key), 404);

        $filename = 'segment-'.$key.'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($key): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['user_id', 'name', 'email', 'primary_goal', 'last_seen_at', 'sessions_30d', 'food_logs_30d', 'weight_logs', 'paid']);

            Segments::query($key)
                ->with('user:id,name,email,phone,username')
                ->chunkById(500, function ($traits) use ($out): void {
                    foreach ($traits as $trait) {
                        fputcsv($out, [
                            $trait->user_id,
                            $trait->user->name,
                            $trait->user->email ?? $trait->user->phone ?? $trait->user->username,
                            $trait->primary_goal,
                            $trait->last_seen_at?->toDateTimeString(),
                            $trait->sessions_30d,
                            $trait->food_logs_30d,
                            $trait->weight_logs_count,
                            $trait->paid ? 1 : 0,
                        ]);
                    }
                }, 'user_id');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
