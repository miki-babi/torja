<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    /**
     * Search members by username/name for the add-friend dialog.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        abort_if($q === '', 422);

        $me = $request->user();
        $friends = Friendship::where('user_id', $me->id)->orWhere('friend_id', $me->id)
            ->get(['user_id', 'friend_id'])
            ->flatMap(fn (Friendship $f): array => [$f->user_id, $f->friend_id])
            ->unique();

        return response()->json([
            'users' => User::where('id', '!=', $me->id)
                ->whereNotIn('id', $friends->all())
                ->where(fn ($query) => $query
                    ->where('username', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%"))
                ->limit(15)
                ->get(['id', 'name', 'username'])
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->username ?: $u->name,
                ])
                ->all(),
        ]);
    }

    /**
     * Send a friend request.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $me = $request->user();

        abort_if($data['user_id'] === $me->id, 422);

        $existing = Friendship::where(fn ($q) => $q
            ->where('user_id', $me->id)->where('friend_id', $data['user_id']))
            ->orWhere(fn ($q) => $q
                ->where('user_id', $data['user_id'])->where('friend_id', $me->id))
            ->first();

        if ($existing !== null) {
            if ($existing->status === 'pending' && $existing->friend_id === $me->id) {
                $existing->update(['status' => 'accepted']);
            }

            return response()->json(['friendship' => $existing->refresh()], 200);
        }

        return response()->json([
            'friendship' => Friendship::create([
                'user_id' => $me->id,
                'friend_id' => $data['user_id'],
            ]),
        ], 201);
    }

    /**
     * Accept an incoming request — recipient only.
     */
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        abort_unless($friendship->friend_id === $request->user()->id, 403);
        $friendship->update(['status' => 'accepted']);

        return response()->json(['friendship' => $friendship]);
    }

    /**
     * Decline a request or remove a friend — either party.
     */
    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        abort_unless(
            in_array($request->user()->id, [$friendship->user_id, $friendship->friend_id], true),
            403,
        );
        $friendship->delete();

        return response()->json(['ok' => true]);
    }
}
