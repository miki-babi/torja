<?php

namespace App\Models;

use Database\Factories\ExerciseProgramWorkoutItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One exercise entry inside an ExerciseProgramWorkout. `exercise_id` matches
 * the exercises table slug, same as ExercisePlaylistItem.
 *
 * @property int $id
 * @property int $exercise_program_workout_id
 * @property string $exercise_id
 * @property int $position
 * @property int|null $sets
 * @property int|null $reps
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['exercise_program_workout_id', 'exercise_id', 'position', 'sets', 'reps'])]
class ExerciseProgramWorkoutItem extends Model
{
    /** @use HasFactory<ExerciseProgramWorkoutItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sets' => 'integer',
            'reps' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExerciseProgramWorkout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramWorkout::class, 'exercise_program_workout_id');
    }
}
