<?php

namespace App\Support;

use App\Models\WorkoutSet;
use Illuminate\Support\Str;

/**
 * Ranks a set of member ids by all-time lifted volume in one grouped
 * query. Only opted-in members appear; ordering is volume desc.
 */
class LeaderboardRank
{
    /**
     * Ranked entries for the given member ids (opted-in only).
     *
     * @param  array<int>  $userIds
     * @return array<int, array{rank: int, user_id: int, name: string, volume: int, is_you: bool}>
     */
    public static function entriesFor(array $userIds, int $viewerId): array
    {
        $volumes = WorkoutSet::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'workout_sets.workout_session_id')
            ->join('users', 'users.id', '=', 'workout_sessions.user_id')
            ->whereIn('workout_sessions.user_id', $userIds)
            ->where('users.leaderboard_opted_in', true)
            ->groupBy('workout_sessions.user_id', 'users.name', 'users.username')
            ->selectRaw('workout_sessions.user_id, users.name, users.username, SUM(COALESCE(workout_sets.weight_kg, 0) * workout_sets.reps) as total')
            ->orderByDesc('total')
            ->get();

        $entries = [];
        $rank = 0;
        foreach ($volumes as $row) {
            $rank++;
            $entries[] = [
                'rank' => $rank,
                'user_id' => (int) $row->user_id,
                'name' => $row->username ?: (string) Str::before($row->name, ' '),
                'volume' => (int) round($row->total),
                'is_you' => (int) $row->user_id === $viewerId,
            ];
        }

        return $entries;
    }
}
