<?php

namespace App\Models;

use Database\Factories\ExerciseProgramAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's claim on an ExerciseProgram — recorded for both free saves
 * (payment_id null) and paid purchases (payment_id set). Its existence is
 * what makes copying idempotent: the playlists are copied exactly once.
 *
 * @property int $id
 * @property int $user_id
 * @property int $exercise_program_id
 * @property int|null $payment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'exercise_program_id', 'payment_id'])]
class ExerciseProgramAccess extends Model
{
    /** @use HasFactory<ExerciseProgramAccessFactory> */
    use HasFactory;

    /**
     * The table associated with the model ("access" has no plural).
     *
     * @var string
     */
    protected $table = 'exercise_program_access';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExerciseProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    /**
     * The payment that granted this claim, when the program was paid.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
