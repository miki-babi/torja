<?php

namespace App\Support\Telegram;

use App\Models\User;
use App\Support\Segments;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves a broadcast's `audience` config — `{everyone, segments[],
 * user_ids[]}` — to the query of Telegram-reachable users it should hit.
 * Segment and hand-picked ids union; `everyone` short-circuits to every
 * user with a Telegram identity.
 */
class BroadcastAudience
{
    /**
     * The user query for an audience config.
     *
     * @param  array{everyone?: bool, segments?: array<int, string>, user_ids?: array<int, int>}  $audience
     * @return Builder<User>
     */
    public static function query(array $audience): Builder
    {
        $query = User::query()->telegramReachable();

        if ($audience['everyone'] ?? false) {
            return $query;
        }

        $segmentKeys = array_filter(
            $audience['segments'] ?? [],
            fn (string $key): bool => Segments::exists($key),
        );

        $ids = collect($audience['user_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id);

        foreach ($segmentKeys as $key) {
            $ids = $ids->merge(Segments::query($key)->pluck('user_id'));
        }

        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $ids);
    }

    /**
     * The audience's live recipient count — feeds the composer's preview.
     *
     * @param  array{everyone?: bool, segments?: array<int, string>, user_ids?: array<int, int>}  $audience
     */
    public static function count(array $audience): int
    {
        return self::query($audience)->count();
    }

    /**
     * Human-readable audience summary for list views.
     *
     * @param  array{everyone?: bool, segments?: array<int, string>, user_ids?: array<int, int>}  $audience
     */
    public static function describe(array $audience): string
    {
        if ($audience['everyone'] ?? false) {
            return 'Everyone on Telegram';
        }

        $parts = [];
        $labels = Segments::labels();

        foreach ($audience['segments'] ?? [] as $key) {
            if (isset($labels[$key])) {
                $parts[] = $labels[$key];
            }
        }

        $userCount = count($audience['user_ids'] ?? []);

        if ($userCount > 0) {
            $parts[] = $userCount.' picked user'.($userCount === 1 ? '' : 's');
        }

        return $parts === [] ? 'No recipients' : implode(' + ', $parts);
    }
}
