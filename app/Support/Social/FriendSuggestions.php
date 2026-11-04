<?php

namespace App\Support\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserTrait;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;

/**
 * Scores unconnected members from the member's social graph for the
 * "People you may know" lists. Each suggestion carries a `reason` the
 * UI renders as a subtitle — the strongest signal that earned the spot.
 *
 * Signals (weight): mutual friends +5 each (cap 15), same gym +6,
 * shared group +4 each, nearby (≤10 km) +3, same referrer +2, wider
 * area (≤50 km) +1, recently active +1. Remaining slots fill with the
 * newest members (reason `new`).
 */
class FriendSuggestions
{
    /**
     * @return list<array{id: int, name: string, avatar: ?string, reason: array{key: string, name?: string, count?: int}}>
     */
    public function for(User $user, int $limit = 10): array
    {
        $friendIds = $user->friendIds();

        // Every member with any friendship row touching the viewer —
        // pending or accepted, either direction — is excluded.
        $connected = Friendship::where('user_id', $user->id)
            ->orWhere('friend_id', $user->id)
            ->get(['user_id', 'friend_id'])
            ->flatMap(fn (Friendship $f): array => [$f->user_id, $f->friend_id])
            ->push($user->id)
            ->unique()
            ->all();

        /** @var array<int, int> $scores */
        $scores = [];

        /** @var array<int, array{key: string, name?: string, count?: int}> $reasons */
        $reasons = [];

        $mutuals = $this->mutualFriendCounts($user, $friendIds);
        foreach ($mutuals as $id => $count) {
            $scores[$id] = min(15, 5 * $count);
            $reasons[$id] = ['key' => 'mutual_friends', 'count' => $count];
        }

        if ($user->gym_id !== null) {
            $mates = User::where('gym_id', $user->gym_id)
                ->whereNotIn('id', $connected)
                ->pluck('id');

            foreach ($mates as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 6;
                $reasons[$id] ??= ['key' => 'gym', 'name' => (string) $user->gym?->name];
            }
        }

        $groups = $user->groups()->get(['groups.id', 'groups.name'])->keyBy('id');

        if ($groups->isNotEmpty()) {
            $mates = DB::table('group_user')
                ->whereIn('group_id', $groups->keys()->all())
                ->where('user_id', '!=', $user->id)
                ->whereNotIn('user_id', $connected)
                ->get(['group_id', 'user_id']);

            foreach ($mates as $mate) {
                $scores[$mate->user_id] = ($scores[$mate->user_id] ?? 0) + 4;
                $reasons[$mate->user_id] ??= [
                    'key' => 'group',
                    'name' => (string) ($groups[$mate->group_id]->name ?? ''),
                ];
            }
        }

        if ($user->latitude !== null && $user->longitude !== null) {
            $locals = User::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereNotIn('id', $connected)
                ->get(['id', 'latitude', 'longitude']);

            foreach ($locals as $local) {
                $km = Geo::haversineKm(
                    $user->latitude,
                    $user->longitude,
                    $local->latitude,
                    $local->longitude,
                );

                if ($km <= 10) {
                    $scores[$local->id] = ($scores[$local->id] ?? 0) + 3;
                    $reasons[$local->id] ??= [
                        'key' => 'nearby',
                        'name' => (string) round($km, 1),
                    ];
                } elseif ($km <= 50) {
                    $scores[$local->id] = ($scores[$local->id] ?? 0) + 1;
                }
            }
        }

        if ($user->referred_by_id !== null) {
            $siblings = User::where('referred_by_id', $user->referred_by_id)
                ->whereNotIn('id', $connected)
                ->pluck('id');

            foreach ($siblings as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 2;
            }
        }

        // Activity boost: keep suggestions skewed toward members who
        // actually train, so dormant accounts don't dominate.
        $candidates = array_keys($scores);

        if ($candidates !== []) {
            $active = UserTrait::whereIn('user_id', $candidates)
                ->where('sessions_30d', '>', 0)
                ->pluck('user_id');

            foreach ($active as $id) {
                $scores[$id] += 1;
            }
        }

        arsort($scores);
        $picked = array_slice(array_keys($scores), 0, $limit);

        // Top up with the newest unconnected members so the list is never
        // empty for members with a thin graph.
        if (count($picked) < $limit) {
            $fill = User::whereNotIn('id', $connected)
                ->whereNotIn('id', $picked)
                ->latest('id')
                ->limit($limit - count($picked))
                ->pluck('id')
                ->all();

            $picked = [...$picked, ...$fill];
        }

        $members = User::whereIn('id', $picked)
            ->get(['id', 'name', 'username', 'avatar_path'])
            ->keyBy('id');

        $suggestions = [];

        foreach ($picked as $id) {
            $member = $members->get($id);

            if ($member === null) {
                continue;
            }

            $suggestions[] = [
                'id' => $member->id,
                'name' => $member->username ?: $member->name,
                'avatar' => $member->avatar,
                'reason' => $reasons[$id] ?? ['key' => 'new'],
            ];
        }

        return $suggestions;
    }

    /**
     * Accepted friends-of-friends, counted per candidate. Edges between
     * two of the viewer's own friends and edges back to the viewer are
     * ignored.
     *
     * @param  array<int>  $friendIds
     * @return array<int, int>
     */
    private function mutualFriendCounts(User $user, array $friendIds): array
    {
        if ($friendIds === []) {
            return [];
        }

        $counts = [];

        Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q
                ->whereIn('user_id', $friendIds)
                ->orWhereIn('friend_id', $friendIds))
            ->get(['user_id', 'friend_id'])
            ->each(function (Friendship $f) use ($user, $friendIds, &$counts): void {
                foreach ([$f->user_id, $f->friend_id] as $side) {
                    if ($side === $user->id || in_array($side, $friendIds, true)) {
                        continue;
                    }

                    $counts[$side] = ($counts[$side] ?? 0) + 1;
                }
            });

        return $counts;
    }
}
