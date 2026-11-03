<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;
use App\Support\LeaderboardRank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RanksController extends Controller
{
    /**
     * The Ranks page: opt-in gate plus the requested scope's board.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $scope = in_array($request->query('scope'), ['friends', 'groups', 'gym'], true)
            ? $request->query('scope')
            : 'friends';

        $props = [
            'optedIn' => (bool) $user->leaderboard_opted_in,
            'scope' => $scope,
            'gymName' => $user->gym_name,
            'entries' => [],
            'groups' => [],
            'pendingIn' => [],
            'pendingOut' => [],
            'you' => null,
        ];

        if (! $user->leaderboard_opted_in) {
            return Inertia::render('member/ranks', $props);
        }

        if ($scope === 'friends') {
            $props['entries'] = LeaderboardRank::entriesFor(
                [...$user->friendIds(), $user->id],
                $user->id,
            );
            $props['pendingIn'] = Friendship::where('friend_id', $user->id)
                ->where('status', 'pending')
                ->with('user:id,name,username')
                ->get()
                ->map(fn (Friendship $f): array => [
                    'id' => $f->id,
                    'name' => $f->user->username ?: $f->user->name,
                ])
                ->all();
            $props['pendingOut'] = Friendship::where('user_id', $user->id)
                ->where('status', 'pending')
                ->with('friend:id,name,username')
                ->get()
                ->map(fn (Friendship $f): array => [
                    'id' => $f->id,
                    'name' => $f->friend->username ?: $f->friend->name,
                ])
                ->all();
        } elseif ($scope === 'groups') {
            $groups = $user->groups()->withCount('members')->get();
            $props['groups'] = $groups->map(fn (Group $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'invite_code' => $g->invite_code,
                'members' => $g->members_count,
                'is_owner' => $g->owner_id === $user->id,
            ])->all();

            $selected = (int) $request->query('group', $groups->first()?->id ?? 0);
            $group = $groups->firstWhere('id', $selected);
            if ($group !== null) {
                $props['selectedGroup'] = $group->id;
                $props['entries'] = LeaderboardRank::entriesFor(
                    $group->members()->pluck('users.id')->all(),
                    $user->id,
                );
            }
        } else {
            if ($user->gym_name !== null && $user->gym_name !== '') {
                $mates = User::where('leaderboard_opted_in', true)
                    ->whereRaw('LOWER(gym_name) = ?', [mb_strtolower($user->gym_name)])
                    ->pluck('id')
                    ->all();
                $props['entries'] = LeaderboardRank::entriesFor($mates, $user->id);
            }
        }

        $props['you'] = collect($props['entries'])->firstWhere('is_you', true);

        return Inertia::render('member/ranks', $props);
    }

    /**
     * Join the leaderboards — opted-in members appear on all three boards.
     */
    public function optIn(Request $request): JsonResponse
    {
        $request->user()->update(['leaderboard_opted_in' => true]);

        return response()->json(['optedIn' => true]);
    }

    /**
     * Leave the leaderboards.
     */
    public function optOut(Request $request): JsonResponse
    {
        $request->user()->update(['leaderboard_opted_in' => false]);

        return response()->json(['optedIn' => false]);
    }
}
