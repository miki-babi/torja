<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymController extends Controller
{
    /**
     * Set the member's gym name for the gym-scope leaderboard. An empty
     * value removes them from the gym board.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['gym_name' => ['nullable', 'string', 'max:60']]);

        $gym = trim((string) ($data['gym_name'] ?? ''));
        $request->user()->update(['gym_name' => $gym === '' ? null : $gym]);

        return response()->json(['gymName' => $request->user()->gym_name]);
    }
}
