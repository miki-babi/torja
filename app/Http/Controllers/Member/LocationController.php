<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Store the member's shared coordinates — powers "People you may
     * know" proximity reasons and the nearby-gyms sort in the picker.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $request->user()->forceFill([
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'location_shared_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }
}
