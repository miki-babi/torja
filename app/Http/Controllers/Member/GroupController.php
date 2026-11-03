<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Create a named squad; the creator becomes its first member and the
     * squad's chat room is provisioned alongside.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);

        $group = Group::create([
            'name' => $data['name'],
            'owner_id' => $request->user()->id,
        ]);
        $group->members()->attach($request->user()->id);

        $conversation = Conversation::forGroup($group);
        $conversation->addParticipant($request->user());

        return response()->json(['group' => $group, 'chat_id' => $conversation->id], 201);
    }

    /**
     * Join a squad by invite code or straight off the discovery cards —
     * seat in the chat room included.
     */
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required_without:group_id', 'nullable', 'string', 'max:10'],
            'group_id' => ['required_without:code', 'integer', 'exists:groups,id'],
        ]);

        $group = isset($data['group_id'])
            ? Group::findOrFail($data['group_id'])
            : Group::where('invite_code', strtoupper($data['code']))->firstOrFail();
        $group->members()->syncWithoutDetaching([$request->user()->id]);

        $conversation = Conversation::forGroup($group);
        $conversation->addParticipant($request->user());

        return response()->json(['group' => $group, 'chat_id' => $conversation->id], 201);
    }

    /**
     * Leave a squad; the owner can delete it for everyone. The chat room
     * follows: members lose their seat, deletion cascades the whole room.
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        abort_unless($group->members()->where('users.id', $request->user()->id)->exists(), 403);

        if ($group->owner_id === $request->user()->id) {
            $group->delete();
        } else {
            $group->members()->detach($request->user()->id);
            Conversation::where('group_id', $group->id)->first()?->removeParticipant($request->user());
        }

        return response()->json(['ok' => true]);
    }
}
