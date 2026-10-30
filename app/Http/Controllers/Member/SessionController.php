<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /**
     * Start (or resume) today's session, optionally tied to a playlist.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercise_playlist_id' => ['nullable', 'integer'],
        ]);

        $playlistId = $data['exercise_playlist_id'] ?? null;

        if ($playlistId !== null) {
            $playlist = ExercisePlaylist::query()->whereKey($playlistId)->firstOrFail();
            abort_unless($playlist->user_id === $request->user()->id, 403);
        }

        $session = WorkoutSession::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->where('exercise_playlist_id', $playlistId)
            ->first();

        $resumed = $session !== null;

        $session ??= WorkoutSession::create([
            'user_id' => $request->user()->id,
            'date' => now()->toDateString(),
            'exercise_playlist_id' => $playlistId,
        ]);

        app(Tracker::class)->record('session.started', subject: $session, properties: [
            'playlist_id' => $playlistId,
            'resumed' => $resumed,
        ]);

        return response()->json(['session' => $session->toFrontend()], 201);
    }

    /**
     * Create or update one logged set.
     */
    public function upsertSet(Request $request, WorkoutSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'exercise_id' => ['required', 'string', 'max:100'],
            'set_index' => ['required', 'integer', 'min:0', 'max:50'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'reps' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['exercise_id'] = ExerciseCatalog::resolve($data['exercise_id']);

        validator($data, [
            'exercise_id' => [Rule::exists('exercises', 'slug')],
        ])->validate();

        $session->sets()->updateOrCreate(
            [
                'exercise_id' => $data['exercise_id'],
                'set_index' => $data['set_index'],
            ],
            [
                'weight_kg' => $data['weight_kg'] ?? null,
                'reps' => $data['reps'] ?? null,
            ],
        );

        app(Tracker::class)->record('workout.set_logged', subject: $session, properties: [
            'exercise_id' => $data['exercise_id'],
            'has_weight' => ($data['weight_kg'] ?? null) !== null,
            'has_reps' => ($data['reps'] ?? null) !== null,
        ]);

        return response()->json(['session' => $session->refresh()->toFrontend()]);
    }

    /**
     * Quick-log one set for an exercise into today's free session, creating
     * the session on demand. Backs the "Log a set" shortcut on the explore
     * and exercise detail screens so a set can be saved without starting a
     * session first.
     */
    public function logSet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercise_id' => ['required', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'reps' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $data['exercise_id'] = ExerciseCatalog::resolve($data['exercise_id']);

        validator($data, [
            'exercise_id' => [Rule::exists('exercises', 'slug')],
        ])->validate();

        $session = WorkoutSession::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->whereNull('exercise_playlist_id')
            ->first();

        $session ??= WorkoutSession::create([
            'user_id' => $request->user()->id,
            'date' => now()->toDateString(),
            'exercise_playlist_id' => null,
        ]);

        // Sets keep their index after mid-list deletes, so the next index is
        // max + 1 rather than count — count would collide on the unique key.
        $maxIndex = $session->sets()
            ->where('exercise_id', $data['exercise_id'])
            ->max('set_index');

        $nextIndex = $maxIndex === null ? 0 : $maxIndex + 1;

        $session->sets()->create([
            'exercise_id' => $data['exercise_id'],
            'set_index' => $nextIndex,
            'weight_kg' => $data['weight_kg'] ?? null,
            'reps' => $data['reps'],
        ]);

        app(Tracker::class)->record('workout.set_logged', subject: $session, properties: [
            'exercise_id' => $data['exercise_id'],
            'quick' => true,
            'has_weight' => ($data['weight_kg'] ?? null) !== null,
            'has_reps' => true,
        ]);

        return response()->json(['session' => $session->refresh()->toFrontend()], 201);
    }

    /**
     * "Add exercise" picker page for an active session: search the catalog and
     * pick one to log a set against, then return to the referring screen.
     */
    public function exercises(Request $request, WorkoutSession $session): Response
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $back = (string) $request->query('back', '');

        return Inertia::render('member/session-add-exercise', [
            'session' => $session->load('sets')->toFrontend(),
            'backUrl' => str_starts_with($back, '/') ? $back : route('member.exercise'),
            'bodyParts' => ExerciseCatalog::bodyPartFilters(),
            'equipment' => Exercise::query()->distinct()->orderBy('equipment')->pluck('equipment')->all(),
        ]);
    }

    /**
     * Remove one logged set.
     */
    public function destroySet(Request $request, WorkoutSession $session, WorkoutSet $set): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_unless($set->workout_session_id === $session->id, 404);

        $set->delete();

        return response()->json(['session' => $session->refresh()->toFrontend()]);
    }
}
