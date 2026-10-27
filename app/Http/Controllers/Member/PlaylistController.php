<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ExercisePlaylist;
use App\Models\ShortLink;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    /**
     * Detail screen for one playlist: its exercises, rename/delete and
     * session-start actions.
     */
    public function show(Request $request, ExercisePlaylist $playlist): Response
    {
        $this->authorizeOwnership($request, $playlist);

        $user = $request->user();

        $sessions = $user->workoutSessions()
            ->with('sets')
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $exerciseIds = $playlist->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        return Inertia::render('member/playlist-show', [
            'playlist' => $playlist->load(['items', 'program'])->toFrontend(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
        ]);
    }

    /**
     * Create a playlist owned by the current user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $playlist = $request->user()->exercisePlaylists()->create($data);

        app(Tracker::class)->record('playlist.created', subject: $playlist);

        return response()->json(['playlist' => $playlist->toFrontend()], 201);
    }

    /**
     * Rename a playlist.
     */
    public function update(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $playlist->update($data);

        return response()->json(['playlist' => $playlist->toFrontend()]);
    }

    /**
     * Delete a playlist and its items.
     */
    public function destroy(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        $playlist->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Add an exercise to a playlist (idempotent).
     */
    public function addExercise(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        $data = $request->validate([
            'exercise_id' => ['required', 'string', 'max:100'],
        ]);

        $data['exercise_id'] = ExerciseCatalog::resolve($data['exercise_id']);

        validator($data, [
            'exercise_id' => [Rule::exists('exercises', 'slug')],
        ])->validate();

        $exists = $playlist->items()->where('exercise_id', $data['exercise_id'])->exists();

        if (! $exists) {
            $playlist->items()->create([
                'exercise_id' => $data['exercise_id'],
                'position' => ((int) $playlist->items()->max('position')) + 1,
            ]);

            app(Tracker::class)->record('playlist.exercise_added', subject: $playlist, properties: [
                'exercise_id' => $data['exercise_id'],
            ]);
        }

        return response()->json(['playlist' => $playlist->refresh()->toFrontend()]);
    }

    /**
     * Remove an exercise from a playlist.
     */
    public function removeExercise(Request $request, ExercisePlaylist $playlist, string $exercise): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        $exerciseId = ExerciseCatalog::resolve($exercise);

        $playlist->items()->where('exercise_id', $exerciseId)->firstOrFail()->delete();

        app(Tracker::class)->record('playlist.exercise_removed', subject: $playlist, properties: [
            'exercise_id' => $exerciseId,
        ]);

        return response()->json(['playlist' => $playlist->refresh()->toFrontend()]);
    }

    /**
     * Enable sharing for a custom playlist: mints (or reuses) the share token
     * and returns the public URL.
     */
    public function share(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        abort_if($playlist->generated_for !== null, 403);

        $playlist->update([
            'share_token' => $playlist->share_token ?? (string) Str::uuid(),
        ]);

        app(Tracker::class)->record('playlist.shared', subject: $playlist);

        return response()->json(['share_url' => $playlist->shareUrl()]);
    }

    /**
     * Disable sharing: clears the token so the public link stops working.
     */
    public function unshare(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        $this->authorizeOwnership($request, $playlist);

        $playlist->update(['share_token' => null]);
        ShortLink::forget($playlist);

        app(Tracker::class)->record('playlist.unshared', subject: $playlist);

        return response()->json(['share_url' => null]);
    }

    private function authorizeOwnership(Request $request, ExercisePlaylist $playlist): void
    {
        abort_unless($playlist->user_id === $request->user()->id, 403);
    }
}
