<?php

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One day of logged work — optionally tied to a playlist. Ad-hoc sessions
 * have a null exercise_playlist_id.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $exercise_playlist_id
 * @property Carbon $date
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'exercise_playlist_id', 'date', 'finished_at'])]
class WorkoutSession extends Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Starting a session finishes any other open session from the same day
     * — only one workout can be active at a time.
     */
    protected static function booted(): void
    {
        static::created(function (WorkoutSession $session): void {
            static::where('user_id', $session->user_id)
                ->whereDate('date', $session->date)
                ->whereKeyNot($session->id)
                ->whereNull('finished_at')
                ->update(['finished_at' => now()]);
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finished_at' => 'datetime',
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
     * The shared recap post for this session, when the member published one.
     *
     * @return HasOne<Recap, $this>
     */
    public function recap(): HasOne
    {
        return $this->hasOne(Recap::class);
    }

    /**
     * Frontend payload for the member exercise page.
     *
     * @return array{id: int, exercise_playlist_id: int|null, date: string, has_recap: bool, sets: array<int, array{id: int, workout_session_id: int, exercise_id: string, set_index: int, weight_kg: float|null, reps: int|null}>}
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'exercise_playlist_id' => $this->exercise_playlist_id,
            'date' => $this->date->toDateString(),
            // Reads the eager `recap_exists` aggregate when the query asked
            // for it, so session lists don't N+1 on this flag.
            'has_recap' => (bool) ($this->recap_exists ?? $this->recap()->exists()),
            'sets' => $this->sets
                ->map(fn (WorkoutSet $set): array => [
                    'id' => $set->id,
                    'workout_session_id' => $set->workout_session_id,
                    'exercise_id' => $set->exercise_id,
                    'set_index' => $set->set_index,
                    'weight_kg' => $set->weight_kg === null ? null : (float) $set->weight_kg,
                    'reps' => $set->reps,
                ])
                ->all(),
        ];
    }
}
