<?php

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One day of logged work — optionally tied to a playlist. Ad-hoc sessions
 * have a null exercise_playlist_id.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $exercise_playlist_id
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'exercise_playlist_id', 'date'])]
class WorkoutSession extends Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExercisePlaylist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(ExercisePlaylist::class, 'exercise_playlist_id');
    }

    /**
     * The logged sets, ordered by exercise then set index.
     *
     * @return HasMany<WorkoutSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)
            ->orderBy('exercise_id')
            ->orderBy('set_index');
    }

    /**
     * Frontend payload for the member exercise page.
     *
     * @return array{id: int, exercise_playlist_id: int|null, date: string, sets: array<int, array{id: int, exercise_id: string, set_index: int, weight_kg: float|null, reps: int|null}>}
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'exercise_playlist_id' => $this->exercise_playlist_id,
            'date' => $this->date->toDateString(),
            'sets' => $this->sets
                ->map(fn (WorkoutSet $set): array => [
                    'id' => $set->id,
                    'exercise_id' => $set->exercise_id,
                    'set_index' => $set->set_index,
                    'weight_kg' => $set->weight_kg === null ? null : (float) $set->weight_kg,
                    'reps' => $set->reps,
                ])
                ->all(),
        ];
    }
}
