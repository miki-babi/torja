<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SharedPlaylistController extends Controller
{
    /**
     * Public read-only view of a shared playlist, addressed by its share
     * token. Guests can view; saving a copy requires login.
     */
    public function show(Request $request, ExercisePlaylist $playlist): Response
    {
        $playlist->load(['items', 'user']);

        app(Tracker::class)->record('shared_playlist.viewed', subject: $playlist, properties: [
            'owner_id' => $playlist->user_id,
            'is_owner' => $request->user()?->id === $playlist->user_id,
        ]);

        $exercises = Exercise::query()
            ->whereIn('slug', $playlist->items->pluck('exercise_id'))
            ->get()
            ->keyBy('slug');

        return Inertia::render('member/shared-playlist', [
            'playlist' => [
                'name' => $playlist->name,
                'owner' => $playlist->user->name,
                'exercises' => $playlist->items
                    ->map(fn ($item) => $exercises->get($item->exercise_id)?->toSummary())
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'isOwner' => $request->user()?->id === $playlist->user_id,
            'saveUrl' => route('playlists.shared.save', $playlist->share_token),
            'playlistUrl' => route('member.playlists.show', $playlist),
        ]);
    }

    /**
     * Save an independent copy of the shared playlist into the recipient's
     * account. Owners saving their own link get their own playlist back.
     */
    public function save(Request $request, ExercisePlaylist $playlist): JsonResponse
    {
        if ($request->user()->id === $playlist->user_id) {
            return response()->json(['playlist_id' => $playlist->id]);
        }

        app(Tracker::class)->record('shared_playlist.saved', subject: $playlist, properties: [
            'owner_id' => $playlist->user_id,
        ]);

        $copy = DB::transaction(function () use ($request, $playlist): ExercisePlaylist {
            $copy = $request->user()->exercisePlaylists()->create([
                'name' => $playlist->name,
            ]);

            $copy->items()->createMany(
                $playlist->items
                    ->map(fn ($item): array => [
                        'exercise_id' => $item->exercise_id,
                        'position' => $item->position,
                    ])
                    ->all(),
            );

            return $copy;
        });

        return response()->json(['playlist_id' => $copy->id], 201);
    }
}
