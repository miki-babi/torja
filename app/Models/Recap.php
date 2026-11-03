<?php

namespace App\Models;

use App\Support\ExerciseCatalog;
use Database\Factories\RecapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A shared workout recap post on the member home feed. One recap per session
 * (unique key), with optional photo and caption plus a computed exercise
 * summary. Other members react with bravos and comments.
 *
 * @property int $id
 * @property int $user_id
 * @property int $workout_session_id
 * @property string|null $caption
 * @property string|null $image_path
 * @property int|null $bravos_count
 * @property int|null $comments_count
 * @property bool|null $bravoed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'workout_session_id', 'caption', 'image_path'])]
class Recap extends Model
{
    /** @use HasFactory<RecapFactory> */
    use HasFactory;

    /**
     * The member who posted the recap.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The logged session this recap summarizes.
     *
     * @return BelongsTo<WorkoutSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    /**
     * Bravo reactions on the post.
     *
     * @return HasMany<RecapBravo, $this>
     */
    public function bravos(): HasMany
    {
        return $this->hasMany(RecapBravo::class);
    }

    /**
     * Comments on the post, newest first.
     *
     * @return HasMany<RecapComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(RecapComment::class)->latest('id');
    }

    /**
     * Public URL for the uploaded photo, when one was attached.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
    }

    /**
     * Exercise summary computed from the linked session: title, duration,
     * totals, and one row per exercise with its best set. `title` is null for
     * free sessions — the frontend renders the localized fallback.
     *
     * @return array{title: string|null, duration_minutes: int|null, exercise_count: int, set_count: int, volume_kg: float, exercises: array<int, array{name: string, sets: int, weight_kg: float|null, reps: int|null}>}
     */
    public function summary(): array
    {
        $session = $this->session;
        $sets = $session->sets;
        $names = ExerciseCatalog::namesFor(
            $sets->pluck('exercise_id')->unique()->values()->all(),
        );

        $exercises = $sets->groupBy('exercise_id')
            ->map(function ($exerciseSets, string $exerciseId) use ($names): array {
                $best = $exerciseSets
                    ->sortByDesc(fn (WorkoutSet $set): float => ($set->weight_kg ?? 0) * 1000 + ($set->reps ?? 0))
                    ->first();

                return [
                    'name' => $names[$exerciseId]['name'] ?? $exerciseId,
                    'sets' => $exerciseSets->count(),
                    'weight_kg' => $best?->weight_kg === null ? null : (float) $best->weight_kg,
                    'reps' => $best?->reps,
                ];
            })
            ->values()
            ->all();

        return [
            'title' => $session->playlist?->name,
            'duration_minutes' => $session->finished_at === null
                ? null
                : max(1, (int) $session->created_at->diffInMinutes($session->finished_at)),
            'exercise_count' => count($exercises),
            'set_count' => $sets->count(),
            'volume_kg' => (float) $sets->sum(
                fn (WorkoutSet $set): float => ($set->weight_kg ?? 0) * ($set->reps ?? 0),
            ),
            'exercises' => $exercises,
        ];
    }

    /**
     * Feed payload for one viewer. `bravos_count`, `comments_count` and
     * `bravoed` read eager-loaded aggregates when present and fall back to
     * queries, so single-recap responses stay correct without ceremony.
     *
     * @return array<string, mixed>
     */
    public function toFeed(User $viewer): array
    {
        $owned = $this->user_id === $viewer->id;

        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'image_url' => $this->imageUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'summary' => $this->summary(),
            'bravos_count' => (int) ($this->bravos_count ?? $this->bravos()->count()),
            'bravoed' => (bool) ($this->bravoed ?? $this->bravos()->where('user_id', $viewer->id)->exists()),
            'comments_count' => (int) ($this->comments_count ?? $this->comments()->count()),
            'comments' => $this->comments
                ->map(fn (RecapComment $comment): array => $comment->toFeed($viewer, $owned))
                ->all(),
            'can_edit' => $owned,
            'can_delete' => $owned,
        ];
    }
}
