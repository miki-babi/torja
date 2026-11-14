<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's catalog of free programs: every shared, zero-price program
 * published by each of their coaches. Claiming happens on the public
 * shared page, which keeps the lead form and bot redirect intact.
 */
class FreeProgramsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('member/free-programs', [
            'coaches' => $request->user()->trainers()
                ->with('user:id,avatar_path')
                ->get()
                ->map(fn (Trainer $trainer): array => [
                    'name' => $trainer->display_name,
                    'avatar' => $trainer->user?->avatar,
                    'exercise' => $trainer->user?->exercisePrograms()
                        ->whereNotNull('share_token')
                        ->where('price_etb', 0)
                        ->withCount('workouts')
                        ->latest()
                        ->get()
                        ->map(fn (ExerciseProgram $program): array => [
                            'id' => $program->id,
                            'name' => $program->localizedName(),
                            'description' => $program->localizedDescription(),
                            'image_url' => $program->image_url,
                            'items_count' => $program->workouts_count,
                            'url' => route('programs.shared.show', $program->share_token),
                        ])
                        ->all() ?? [],
                    'meal' => $trainer->user?->mealPrograms()
                        ->whereNotNull('share_token')
                        ->where('price_etb', 0)
                        ->withCount('meals')
                        ->latest()
                        ->get()
                        ->map(fn (MealProgram $program): array => [
                            'id' => $program->id,
                            'name' => $program->localizedName(),
                            'description' => $program->localizedDescription(),
                            'image_url' => $program->image_url,
                            'items_count' => $program->meals_count,
                            'url' => route('meal-programs.shared.show', $program->share_token),
                        ])
                        ->all() ?? [],
                ])
                ->all(),
        ]);
    }
}
