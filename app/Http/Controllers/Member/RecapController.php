<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Recap;
use App\Models\RecapComment;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Tracking\Tracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecapController extends Controller
{
    private const PAGE_SIZE = 10;

    private const PREVIEW_COMMENTS = 3;

    /**
     * Latest recaps for the home feed — JSON pagination via a `before`
     * cursor on the id so "load more" stays stable while new posts arrive.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
        ]);

        $recaps = self::feedQuery($request->user())
            ->when(
                $data['before'] ?? null,
                fn (Builder $query, int $before) => $query->where('id', '<', $before),
            )
            ->latest('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        $hasMore = $recaps->count() > self::PAGE_SIZE;
        $recaps = $recaps->take(self::PAGE_SIZE);
        self::hydrateComments($recaps);

        return response()->json([
            'recaps' => $recaps
                ->map(fn (Recap $recap): array => $recap->toFeed($request->user()))
                ->all(),
            'next_cursor' => $hasMore ? $recaps->last()->id : null,
        ]);
    }

    /**
     * Publish a recap for a logged session: optional photo + caption with
     * the exercise summary computed from the session's sets.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workout_session_id' => ['required', 'integer', 'exists:workout_sessions,id'],
            'caption' => ['nullable', 'string', 'max:280'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $session = WorkoutSession::whereKey($data['workout_session_id'])
            ->with('sets')
            ->firstOrFail();
        abort_unless($session->user_id === $request->user()->id, 403);

        if ($session->sets->isEmpty()) {
            throw ValidationException::withMessages([
                'workout_session_id' => 'Log at least one set before sharing a recap.',
            ]);
        }

        if ($session->recap()->exists()) {
            throw ValidationException::withMessages([
                'workout_session_id' => 'This session was already shared.',
            ]);
        }

        $recap = Recap::create([
            'user_id' => $request->user()->id,
            'workout_session_id' => $session->id,
            'caption' => $data['caption'] ?? null,
            'image_path' => $request->file('photo')?->store(
                "recap-images/{$request->user()->id}",
                'public',
            ),
        ]);

        app(Tracker::class)->record('recap.created', subject: $recap, properties: [
            'has_photo' => $recap->image_path !== null,
            'has_caption' => $recap->caption !== null,
        ]);

        return response()->json([
            'recap' => $recap
                ->load(['user', 'session.playlist', 'session.sets'])
                ->toFeed($request->user()),
        ], 201);
    }

    /**
     * Edit the caption on the member's own recap.
     */
    public function update(Request $request, Recap $recap): JsonResponse
    {
        abort_unless($recap->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:280'],
        ]);

        $recap->update(['caption' => $data['caption'] ?? null]);

        return response()->json([
            'recap' => $recap
                ->load(['user', 'session.playlist', 'session.sets'])
                ->toFeed($request->user()),
        ]);
    }

    /**
     * Remove the member's own recap along with its stored photo; bravos
     * and comments cascade with the row.
     */
    public function destroy(Request $request, Recap $recap): JsonResponse
    {
        abort_unless($recap->user_id === $request->user()->id, 403);

        Storage::disk('public')->delete($recap->image_path);
        $recap->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Toggle the viewer's bravo on a recap — create on first tap, delete
     * on second. The unique key keeps races idempotent.
     */
    public function bravo(Request $request, Recap $recap): JsonResponse
    {
        $existing = $recap->bravos()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $bravoed = false;
        } else {
            $recap->bravos()->firstOrCreate(['user_id' => $request->user()->id]);
            $bravoed = true;
        }

        app(Tracker::class)->record('recap.bravo', subject: $recap, properties: [
            'bravoed' => $bravoed,
        ]);

        return response()->json([
            'bravos_count' => $recap->bravos()->count(),
            'bravoed' => $bravoed,
        ]);
    }

    /**
     * Older comments for "view all" — newest-first pages via `before` cursor.
     */
    public function comments(Request $request, Recap $recap): JsonResponse
    {
        $data = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
        ]);

        $comments = $recap->comments()
            ->with('user')
            ->when(
                $data['before'] ?? null,
                fn (Builder $query, int $before) => $query->where('id', '<', $before),
            )
            ->limit(self::PAGE_SIZE)
            ->get();

        $viewerOwnsRecap = $recap->user_id === $request->user()->id;

        return response()->json([
            'comments' => $comments
                ->map(fn (RecapComment $comment): array => $comment->toFeed($request->user(), $viewerOwnsRecap))
                ->all(),
            'next_cursor' => $comments->count() === self::PAGE_SIZE
                ? $comments->last()->id
                : null,
        ]);
    }

    /**
     * Add a comment to a recap.
     */
    public function storeComment(Request $request, Recap $recap): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $recap->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        app(Tracker::class)->record('recap.comment', subject: $recap);

        return response()->json([
            'comment' => $comment->load('user')
                ->toFeed($request->user(), $recap->user_id === $request->user()->id),
            'comments_count' => $recap->comments()->count(),
        ], 201);
    }

    /**
     * Delete a comment — allowed for the comment author or the recap
     * owner moderating their own thread.
     */
    public function destroyComment(Request $request, Recap $recap, RecapComment $comment): JsonResponse
    {
        abort_unless($comment->recap_id === $recap->id, 404);
        abort_unless(
            $comment->user_id === $request->user()->id
                || $recap->user_id === $request->user()->id,
            403,
        );

        $comment->delete();

        return response()->json([
            'ok' => true,
            'comments_count' => $recap->comments()->count(),
        ]);
    }

    /**
     * Feed query shared by the Inertia home prop and the JSON pager.
     *
     * @return Builder<Recap>
     */
    public static function feedQuery(User $viewer): Builder
    {
        return Recap::query()
            ->with(['user', 'session.playlist', 'session.sets'])
            ->withCount(['bravos', 'comments'])
            ->withExists([
                'bravos as bravoed' => fn (Builder $query) => $query->where('user_id', $viewer->id),
            ]);
    }

    /**
     * Attach the newest PREVIEW_COMMENTS comments per recap with one query
     * — a limited eager load can't cap per parent, so group in PHP.
     *
     * @param  Collection<int, Recap>  $recaps
     */
    public static function hydrateComments(Collection $recaps): void
    {
        if ($recaps->isEmpty()) {
            return;
        }

        $grouped = RecapComment::query()
            ->whereIn('recap_id', $recaps->modelKeys())
            ->with('user')
            ->latest('id')
            ->get()
            ->groupBy('recap_id');

        foreach ($recaps as $recap) {
            $recap->setRelation(
                'comments',
                $grouped->get($recap->id, collect())->take(self::PREVIEW_COMMENTS)->values(),
            );
        }
    }
}
