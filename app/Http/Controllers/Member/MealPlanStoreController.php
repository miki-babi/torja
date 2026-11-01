<?php

namespace App\Http\Controllers\Member;

use App\Enums\MatchConfidence;
use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Models\MealPlan;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealPlanStoreController extends Controller
{
    /**
     * Persist a member-built meal plan: a user-owned MealPlan plus its
     * day meals and items, created atomically from the wizard payload.
     * Members type item names themselves — every item is custom text,
     * never a food-database reference.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.meals' => ['required', 'array', 'min:1'],
            'days.*.meals.*.meal_type' => ['required', 'string', Rule::in(array_column(MealType::cases(), 'value'))],
            'days.*.meals.*.items' => ['required', 'array', 'min:1'],
            'days.*.meals.*.items.*.name' => ['nullable', 'string', 'max:120'],
            'days.*.meals.*.items.*.portion_note' => ['nullable', 'string', 'max:120'],
        ]);

        $meals = collect($data['days'])
            ->flatMap(fn (array $day): array => $day['meals']);

        $invalidItem = $meals->contains(function (array $meal): bool {
            $items = $meal['items'] ?? [];

            if ($items === []) {
                return true;
            }

            return collect($items)
                ->contains(fn (array $item): bool => blank($item['name'] ?? null));
        });

        $duplicateType = collect($data['days'])
            ->contains(function (array $day): bool {
                $types = array_column($day['meals'], 'meal_type');

                return count($types) !== count(array_unique($types));
            });

        if ($invalidItem) {
            throw ValidationException::withMessages([
                'days' => [__('member.meal_builder.item_needs_name')],
            ]);
        }

        if ($duplicateType) {
            throw ValidationException::withMessages([
                'days' => [__('member.meal_builder.duplicate_meal_type')],
            ]);
        }

        $plan = DB::transaction(function () use ($request, $data): MealPlan {
            $plan = MealPlan::create([
                'slug' => 'custom-'.Str::random(12),
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'goal' => null,
                'tags' => [],
            ]);

            foreach (array_values($data['days']) as $index => $day) {
                foreach ($day['meals'] as $position => $meal) {
                    $row = $plan->meals()->create([
                        'day' => $index + 1,
                        'meal_type' => MealType::from($meal['meal_type']),
                        'position' => $position,
                    ]);

                    foreach ($meal['items'] ?? [] as $itemPosition => $item) {
                        $row->items()->create([
                            'food_id' => null,
                            'raw_text' => $item['name'],
                            'portion_note' => $item['portion_note'] ?? null,
                            'match_confidence' => MatchConfidence::None,
                            'position' => $itemPosition,
                        ]);
                    }
                }
            }

            return $plan;
        });

        app(Tracker::class)->record('meal.plan_created', subject: $plan, properties: [
            'days' => count($data['days']),
        ]);

        return response()->json([
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
        ], 201);
    }
}
