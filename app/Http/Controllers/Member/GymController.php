<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymController extends Controller
{
    /**
     * Search the shared gym list for the picker, biggest gyms first.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'gyms' => Gym::query()
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->withCount('members')
                ->orderByDesc('members_count')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name'])
                ->map(fn (Gym $gym): array => [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'members' => $gym->members_count,
                ])
                ->all(),
        ]);
    }

    /**
     * Pick an existing gym or create a new one — the member's gym
     * leaderboard scope and gym-chat seat move together.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'min:2', 'max:60', 'required_without:gym_id'],
        ]);

        $gym = isset($data['gym_id'])
            ? Gym::findOrFail((int) $data['gym_id'])
            : Gym::firstOrCreate(
                ['name_key' => mb_strtolower(trim((string) $data['name']))],
                ['name' => trim((string) $data['name']), 'created_by' => $request->user()->id],
            );

        $request->user()->setGym($gym);

        return response()->json([
            'gym' => ['id' => $gym->id, 'name' => $gym->name],
            'chat_id' => Conversation::forGym($gym)->id,
        ]);
    }

    /**
     * Leave the gym — clears the leaderboard scope and gym-chat seat.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->setGym(null);

        return response()->json(['gym' => null]);
    }
}
