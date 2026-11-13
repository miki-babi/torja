<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseSearchController extends Controller
{
    /**
     * Lightweight exercise search for the member explore screen: name search
     * plus body part, equipment and level filters with offset pagination.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $bodyParts = collect((array) $request->query('body_part', []))
            ->flatMap(fn (string $part): array => ExerciseCatalog::GROUPED_PARTS[$part] ?? [$part])
            ->unique()
            ->all();
        $equipment = (array) $request->query('equipment', []);
        $level = trim((string) $request->query('level', ''));
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        $exercises = Exercise::query()
            // The shared catalog plus the searcher's own custom exercises.
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->when($bodyParts !== [], fn ($q) => $q->whereIn('body_part', $bodyParts))
            ->when($equipment !== [], fn ($q) => $q->whereIn('equipment', $equipment))
            ->when($level !== '', fn ($q) => $q->where('level', $level))
            ->orderBy('name');

        $total = $exercises->count();

        return response()->json([
            'exercises' => $exercises->clone()->offset($offset)->limit($limit)->get()->map->toSummary()->all(),
            'total' => $total,
        ]);
    }
}
