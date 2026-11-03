<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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

        $period = in_array($request->query('period'), ['today', 'week', 'all'], true)
            ? $request->query('period')
            : 'today';

        $props = [
            'optedIn' => (bool) $user->leaderboard_opted_in,
            'scope' => $scope,
            'period' => $period,
            'metric' => $period === 'all' ? 'volume' : 'sessions',
            'gym' => $user->gym?->only(['id', 'name']),
            'entries' => [],
            'groups' => [],
            'pendingIn' => [],
            'pendingOut' => [],
            'suggestedUsers' => [],
            'joinableGroups' => [],
            'you' => null,
        ];

        if (! $user->leaderboard_opted_in) {
            return Inertia::render('member/ranks', $props);
        }

        if ($scope === 'friends') {
            $props['entries'] = LeaderboardRank::entriesFor(
                [...$user->friendIds(), $user->id],
                $user->id,
                $period,
            );
            $props['pendingIn'] = Friendship::where('friend_id', $user->id)
                ->where('status', 'pending')
                ->with('user:id,name,username,avatar_path')
                ->get()
                ->map(fn (Friendship $f): array => [
                    'id' => $f->id,
                    'name' => $f->user->username ?: $f->user->name,
                    'avatar' => $f->user->avatar,
                ])
                ->all();
            $props['pendingOut'] = Friendship::where('user_id', $user->id)
                ->where('status', 'pending')
                ->with('friend:id,name,username,avatar_path')
                ->get()
                ->map(fn (Friendship $f): array => [
                    'id' => $f->id,
                    'name' => $f->friend->username ?: $f->friend->name,
                    'avatar' => $f->friend->avatar,
                ])
                ->all();

            $connected = Friendship::where('user_id', $user->id)
                ->orWhere('friend_id', $user->id)
                ->get(['user_id', 'friend_id'])
                ->flatMap(fn (Friendship $f): array => [$f->user_id, $f->friend_id])
                ->unique();

            $props['suggestedUsers'] = User::where('id', '!=', $user->id)
                ->whereNotIn('id', $connected->all())
                ->latest('id')
                ->limit(10)
                ->get(['id', 'name', 'username', 'avatar_path'])
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->username ?: $u->name,
                    'avatar' => $u->avatar,
                ])
                ->all();
        } elseif ($scope === 'groups') {
            $groups = $user->groups()->withCount('members')->get();
            $chatIds = Conversation::whereIn('group_id', $groups->modelKeys())
                ->pluck('id', 'group_id');
            $props['groups'] = $groups->map(fn (Group $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'invite_code' => $g->invite_code,
                'members' => $g->members_count,
                'is_owner' => $g->owner_id === $user->id,
                'chat_id' => $chatIds->get($g->id),
            ])->all();

            $props['joinableGroups'] = Group::whereNotIn('id', $groups->modelKeys())
                ->withCount('members')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (Group $g): array => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'members' => $g->members_count,
                ])
                ->all();

            $selected = (int) $request->query('group', $groups->value('id') ?? 0);
            $group = $groups->firstWhere('id', $selected);
            if ($group !== null) {
                $props['selectedGroup'] = $group->id;
                $props['entries'] = LeaderboardRank::entriesFor(
                    $group->members()->pluck('users.id')->all(),
                    $user->id,
                    $period,
                );
            }
        } else {
            if ($user->gym_id !== null) {
                $mates = User::where('leaderboard_opted_in', true)
                    ->where('gym_id', $user->gym_id)
                    ->pluck('id')
                    ->all();
                $props['entries'] = LeaderboardRank::entriesFor($mates, $user->id, $period);
                $props['gymChatId'] = Conversation::where('gym_id', $user->gym_id)->value('id');
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
