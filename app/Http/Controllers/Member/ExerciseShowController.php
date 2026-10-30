<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseShowController extends Controller
{
    /**
     * Detail screen for one catalog exercise: demo gif, instructions, which
     * playlists contain it and the sessions that logged sets for it. Legacy
     * catalog ids redirect to the canonical slug.
     */
    public function __invoke(Request $request, string $exercise): Response|RedirectResponse
    {
        $resolved = ExerciseCatalog::resolve($exercise);

        if ($resolved !== $exercise && ExerciseCatalog::has($resolved)) {
            return redirect()->route('member.exercises.show', $resolved);
        }

        $model = Exercise::query()->where('slug', $resolved)->firstOrFail();

        $user = $request->user();

        $playlists = $user->exercisePlaylists()
            ->whereNull('generated_for')
            ->with(['items', 'program'])
            ->get();

        $sessions = $user->workoutSessions()
            ->whereHas('sets', fn ($q) => $q->where('exercise_id', $model->slug))
            ->with('sets')
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        $similarMuscle = Exercise::query()
            ->where('target', $model->target)
            ->whereKeyNot($model->id)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $similarEquipment = Exercise::query()
            ->where('equipment', $model->equipment)
            ->whereKeyNot($model->id)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        app(Tracker::class)->record('exercise.viewed', subject: $model, properties: [
            'slug' => $model->slug,
            'equipment' => $model->equipment,
            'body_part' => $model->body_part,
            'level' => $model->level,
        ]);

        return Inertia::render('member/exercise-show', [
            'exercise' => $model->toDetail(),
            'playlists' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'similarMuscle' => $similarMuscle->map->toSummary()->all(),
            'similarEquipment' => $similarEquipment->map->toSummary()->all(),
        ]);
    }
}
