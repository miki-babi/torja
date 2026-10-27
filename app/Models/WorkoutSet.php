<?php

namespace App\Models;

use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One logged set inside a workout session.
 *
 * @property int $id
 * @property int $workout_session_id
 * @property string $exercise_id
 * @property int $set_index
 * @property string|null $weight_kg
 * @property int|null $reps
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workout_session_id', 'exercise_id', 'set_index', 'weight_kg', 'reps'])]
class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:1',
            'reps' => 'integer',
            'set_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkoutSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
