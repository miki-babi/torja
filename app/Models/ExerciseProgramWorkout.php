<?php

namespace App\Models;

use Database\Factories\ExerciseProgramWorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One named workout inside an ExerciseProgram (e.g. "Day 1 — Push"). On claim
 * it becomes an ExercisePlaylist in the member's account.
 *
 * @property int $id
 * @property int $exercise_program_id
 * @property string $title
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['exercise_program_id', 'title', 'position'])]
class ExerciseProgramWorkout extends Model
{
    /** @use HasFactory<ExerciseProgramWorkoutFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ExerciseProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    /**
     * The workout's exercises, in display order.
     *
     * @return HasMany<ExerciseProgramWorkoutItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExerciseProgramWorkoutItem::class, 'exercise_program_workout_id')
            ->orderBy('position');
    }
}
