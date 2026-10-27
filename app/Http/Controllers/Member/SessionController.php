<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
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
