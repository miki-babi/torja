<?php

namespace App\Models;

use Database\Factories\ExercisePlaylistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A named, user-owned collection of exercises (e.g. "Push day"). Exercises are
 * referenced by the frontend exercise-catalog string ids.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $generated_for
 * @property string|null $share_token
 * @property int|null $exercise_program_id
 * @property int|null $exercise_program_workout_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'generated_for', 'share_token', 'exercise_program_id', 'exercise_program_workout_id'])]
class ExercisePlaylist extends Model
{
    /** @use HasFactory<ExercisePlaylistFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_for' => 'date',
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
     * The program this playlist was copied from when the member claimed it.
     *
     * @return BelongsTo<ExerciseProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    /**
     * The program workout (day) this playlist is a copy of.
     *
     * @return BelongsTo<ExerciseProgramWorkout, $this>
     */
    public function programWorkout(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramWorkout::class, 'exercise_program_workout_id');
    }

    /**
     * The exercises in this playlist, in display order.
     *
     * @return HasMany<ExercisePlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExercisePlaylistItem::class)->orderBy('position');
    }

    /**
     * Workout sessions logged against this playlist.
     *
     * @return HasMany<WorkoutSession, $this>
     */
    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /**
     * Frontend payload for the member exercise page.
     *
     * @return array{id: int, name: string, exercise_ids: array<int, string>, generated_for: string|null, share_url: string|null, can_share: bool, program_id: int|null, program_name: string|null, workout_title: string|null}
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'exercise_ids' => $this->items->pluck('exercise_id')->all(),
            'generated_for' => $this->generated_for?->toDateString(),
            'share_url' => $this->shareUrl(),
            'can_share' => $this->generated_for === null,
            'program_id' => $this->exercise_program_id,
            'program_name' => $this->program?->localizedName(),
            'workout_title' => $this->program === null
                ? null
                : Str::after($this->name, "{$this->program->name} — "),
        ];
    }

    /**
     * The public shared-playlist URL, or null when the playlist is not shared.
     * Carries `ref` + utm params so member-to-member sharing is attributed.
     */
    public function shareUrl(): ?string
    {
        if ($this->share_token === null) {
            return null;
        }

        $url = route('playlists.shared.show', $this->share_token).'?'.Arr::query([
            'ref' => (string) $this->user_id,
            'utm_source' => 'member',
            'utm_medium' => 'share',
            'utm_content' => 'playlist',
        ]);

        return ShortLink::for($this, $url)->shortUrl();
    }
}
