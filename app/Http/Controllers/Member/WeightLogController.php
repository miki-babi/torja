<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\WeightLog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeightLogController extends Controller
{
    /**
     * Upsert the user's weight for a day (defaults to today) and record the
     * `weight.logged` engagement event.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'min:20', 'max:500'],
            'logged_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $log = WeightLog::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('logged_on', $data['logged_on'] ?? today()->toDateString())
            ->first();

        if ($log !== null) {
            $log->update(['weight_kg' => $data['weight_kg']]);
        } else {
            $log = WeightLog::create([
                'user_id' => $request->user()->id,
                'logged_on' => $data['logged_on'] ?? today()->toDateString(),
                'weight_kg' => $data['weight_kg'],
            ]);
        }

        app(Tracker::class)->record('weight.logged', subject: $log, properties: [
            'logged_on' => $log->logged_on->toDateString(),
        ]);

        return response()->json(['log' => $log], 201);
    }

    /**
     * Remove one weight log.
     */
    public function destroy(Request $request, WeightLog $weightLog): JsonResponse
    {
        abort_unless($weightLog->user_id === $request->user()->id, 403);

        $weightLog->delete();

        return response()->json(['ok' => true]);
    }
}
