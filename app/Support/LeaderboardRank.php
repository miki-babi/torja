<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ranks a set of member ids by all-time lifted volume. Every opted-in
 * member appears — zero-volume members sort to the bottom — and each
 * entry carries a "days trained in the last 7" streak for the flame
 * column on the boards.
 */
class LeaderboardRank
{
    /**
     * Ranked entries for the given member ids (opted-in only).
     *
     * @param  array<int>  $userIds
     * @return array<int, array{rank: int, user_id: int, name: string, volume: int, streak: int, is_you: bool}>
     */
    public static function entriesFor(array $userIds, int $viewerId): array
    {
        $volumeSub = WorkoutSet::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'workout_sets.workout_session_id')
            ->groupBy('workout_sessions.user_id')
            ->selectRaw('workout_sessions.user_id, SUM(COALESCE(workout_sets.weight_kg, 0) * workout_sets.reps) as total');

        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('leaderboard_opted_in', true)
            ->leftJoinSub($volumeSub, 'v', 'v.user_id', '=', 'users.id')
            ->orderByDesc(DB::raw('COALESCE(v.total, 0)'))
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.username', DB::raw('COALESCE(v.total, 0) as total')]);

        $streaks = WorkoutSession::query()
            ->whereIn('user_id', $userIds)
            ->where('date', '>=', now()->subDays(6)->toDateString())
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT date) as days')
            ->pluck('days', 'user_id');

        $entries = [];
        $rank = 0;
        foreach ($users as $user) {
            $rank++;
            $entries[] = [
                'rank' => $rank,
                'user_id' => $user->id,
                'name' => $user->username ?: (string) Str::before($user->name, ' '),
                'volume' => (int) round($user->total),
                'streak' => (int) ($streaks[$user->id] ?? 0),
                'is_you' => $user->id === $viewerId,
            ];
        }

        return $entries;
    }
}
