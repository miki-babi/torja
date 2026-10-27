<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\FoodDiaryEntry;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FoodDiaryController extends Controller
{
    /**
     * The member's diary entries for one date.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return response()->json([
            'entries' => $request->user()
                ->foodDiaryEntries()
                ->whereDate('logged_on', $data['date'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Persist one diary entry logged from the meal screen.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logged_on' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'kcal' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'food_id' => ['nullable', 'integer', Rule::exists('foods', 'id')],
            'source' => ['required', 'string', Rule::in(['search', 'photo', 'plan_meal', 'plan_swap'])],
            'had_photos' => ['sometimes', 'boolean'],
        ]);

        $entry = $request->user()->foodDiaryEntries()->create($data);

        app(Tracker::class)->record('food.logged', subject: $entry, properties: [
            'source' => $entry->source,
            'food_id' => $entry->food_id,
            'kcal' => $entry->kcal === null ? null : (float) $entry->kcal,
        ]);

        return response()->json(['entry' => $entry], 201);
    }

    /**
     * Remove one diary entry.
     */
    public function destroy(Request $request, FoodDiaryEntry $foodDiaryEntry): JsonResponse
    {
        abort_unless($foodDiaryEntry->user_id === $request->user()->id, 403);

        $foodDiaryEntry->delete();

        return response()->json(['ok' => true]);
    }
}
