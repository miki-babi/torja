<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client-side event sink: accepts a small batch of events posted by the
 * tracking beacon (`sendBeacon`/`fetch keepalive`) for interactions that
 * never reach a Laravel route — scroll depth, copy clicks, local logs.
 */
class BeaconController extends Controller
{
    public function __construct(private readonly Tracker $tracker) {}

    /**
     * Store a batch of client-emitted events.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:20'],
            'events.*.name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.]{2,60}$/'],
            'events.*.properties' => ['nullable', 'array'],
        ]);

        foreach ($data['events'] as $event) {
            $properties = $event['properties'] ?? [];

            if (strlen(json_encode($properties)) > 2048) {
                continue;
            }

            $this->tracker->record($event['name'], properties: $properties);
        }

        return response()->json(['ok' => true]);
    }
}
