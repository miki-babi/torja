<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Create a named squad; the creator becomes its first member.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);

        $group = Group::create([
            'name' => $data['name'],
            'owner_id' => $request->user()->id,
        ]);
        $group->members()->attach($request->user()->id);

        return response()->json(['group' => $group], 201);
    }

    /**
     * Join a squad by invite code.
     */
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $group = Group::where('invite_code', strtoupper($data['code']))->firstOrFail();
        $group->members()->syncWithoutDetaching([$request->user()->id]);

        return response()->json(['group' => $group], 201);
    }

    /**
     * Leave a squad; the owner can delete it for everyone.
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        abort_unless($group->members()->where('users.id', $request->user()->id)->exists(), 403);

        if ($group->owner_id === $request->user()->id) {
            $group->delete();
        } else {
            $group->members()->detach($request->user()->id);
        }

        return response()->json(['ok' => true]);
    }
}
