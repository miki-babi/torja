<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\BodyMeasurement;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    /**
     * Upsert the user's body measurements for a day (defaults to today).
     * Every field is optional individually but the entry as a whole must
     * carry at least one number.
     */
    public function store(Request $request): JsonResponse
    {
        $fields = ['waist_cm', 'chest_cm', 'arm_cm', 'hip_cm', 'thigh_cm'];

        $data = $request->validate(
            collect($fields)
                ->mapWithKeys(fn (string $field): array => [
                    $field => [
                        'required_without_all:'.
                        implode(',', array_diff($fields, [$field])),
                        'nullable',
                        'numeric',
                        'min:10',
                        'max:400',
                    ],
                ])
                ->merge(['logged_on' => ['nullable', 'date', 'before_or_equal:today']])
                ->all(),
        );

        $loggedOn = $data['logged_on'] ?? today()->toDateString();

        $measurement = BodyMeasurement::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('logged_on', $loggedOn)
            ->first();

        $values = collect($fields)
            ->mapWithKeys(fn (string $field): array => [$field => $data[$field] ?? null])
            ->all();

        if ($measurement !== null) {
            $measurement->update($values);
        } else {
            $measurement = BodyMeasurement::create([
                'user_id' => $request->user()->id,
                'logged_on' => $loggedOn,
                ...$values,
            ]);
        }

        app(Tracker::class)->record('measurements.logged', subject: $measurement, properties: [
            'logged_on' => $measurement->logged_on->toDateString(),
        ]);

        return response()->json(['measurement' => $measurement], 201);
    }

    /**
     * Remove one measurement check-in.
     */
    public function destroy(Request $request, BodyMeasurement $measurement): JsonResponse
    {
        abort_unless($measurement->user_id === $request->user()->id, 403);

        $measurement->delete();

        return response()->json(['ok' => true]);
    }
}
