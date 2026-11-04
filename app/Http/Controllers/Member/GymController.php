<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Gym;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymController extends Controller
{
    /**
     * Search the shared gym list for the picker. When the member has
     * shared their location, pinned gyms come back annotated with
     * `distance_km` and sorted nearest-first; unpinned gyms fall back
     * to popularity ordering below them.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $hasLocation = $user->latitude !== null && $user->longitude !== null;

        return response()->json([
            'gyms' => Gym::query()
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->withCount('members')
                ->get(['id', 'name', 'latitude', 'longitude'])
                ->map(fn (Gym $gym): array => [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'members' => $gym->members_count,
                    'distance_km' => $hasLocation && $gym->latitude !== null && $gym->longitude !== null
                        ? round(Geo::haversineKm(
                            $user->latitude,
                            $user->longitude,
                            $gym->latitude,
                            $gym->longitude,
                        ), 1)
                        : null,
                ])
                ->sort(fn (array $a, array $b): int => ($a['distance_km'] === null) <=> ($b['distance_km'] === null)
                        ?: $a['distance_km'] <=> $b['distance_km']
                        ?: $b['members'] <=> $a['members']
                        ?: strcmp($a['name'], $b['name']))
                ->take(15)
                ->values()
                ->all(),
        ]);
    }

    /**
     * Pick an existing gym or create a new one — the member's gym
     * leaderboard scope and gym-chat seat move together. A gym created
     * by a member who shared their location gets pinned to those
     * coordinates so nearby members can find it.
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
                [
                    'name' => trim((string) $data['name']),
                    'created_by' => $request->user()->id,
                    'latitude' => $request->user()->latitude,
                    'longitude' => $request->user()->longitude,
                ],
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
