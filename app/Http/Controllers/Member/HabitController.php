<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    /**
     * Habits management screen with history data for the calendars.
     */
    public function index(Request $request): Response
    {
        $habits = $request->user()
            ->habits()
            ->with(['logs' => fn ($q) => $q->where('date', '>=', today()->subDays(120))])
            ->orderBy('position')
            ->get()
            ->map(fn (Habit $h) => $h->toFrontend())
            ->all();

        return Inertia::render('member/habits', [
            'habits' => $habits,
        ]);
    }

    /**
     * Create a habit.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $position = (int) $request->user()->habits()->max('position') + 1;

        $habit = $request->user()->habits()->create([
            'name' => $data['name'],
            'position' => $position,
        ]);

        app(Tracker::class)->record('habit.created', subject: $habit);

        return response()->json(['habit' => $habit->toFrontend()], 201);
    }

    /**
     * Rename a habit.
     */
    public function update(Request $request, Habit $habit): JsonResponse
    {
        $this->authorizeOwnership($request, $habit);

        $habit->update($request->validate(['name' => ['required', 'string', 'max:80']]));

        return response()->json(['habit' => $habit->toFrontend()]);
    }

    /**
     * Delete a habit (logs cascade).
     */
    public function destroy(Request $request, Habit $habit): JsonResponse
    {
        $this->authorizeOwnership($request, $habit);

        $habit->delete();

        app(Tracker::class)->record('habit.deleted', subject: $habit);

        return response()->json(['deleted' => true]);
    }

    /**
     * Toggle a habit's log for a date (defaults to today).
     */
    public function toggle(Request $request, Habit $habit): JsonResponse
    {
        $this->authorizeOwnership($request, $habit);

        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = $data['date'] ?? today()->toDateString();

        $existing = $habit->logs()->whereDate('date', $date)->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $habit->logs()->create(['date' => $date]);
        }

        app(Tracker::class)->record('habit.toggled', subject: $habit, properties: [
            'date' => $date,
            'done' => $existing === null,
        ]);

        $habit->load('logs');

        return response()->json(['habit' => $habit->toFrontend()]);
    }

    private function authorizeOwnership(Request $request, Habit $habit): void
    {
        abort_unless($habit->user_id === $request->user()->id, 403);
    }
}
