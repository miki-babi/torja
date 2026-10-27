<?php

namespace App\Models;

use Database\Factories\MealProgramAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's claim on a MealProgram — recorded for both free saves
 * (payment_id null) and paid purchases (payment_id set). Its existence is
 * what makes copying idempotent: the meal plan is copied exactly once.
 *
 * @property int $id
 * @property int $user_id
 * @property int $meal_program_id
 * @property int|null $payment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'meal_program_id', 'payment_id'])]
class MealProgramAccess extends Model
{
    /** @use HasFactory<MealProgramAccessFactory> */
    use HasFactory;

    /**
     * The table associated with the model ("access" has no plural).
     *
     * @var string
     */
    protected $table = 'meal_program_access';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MealProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MealProgram::class, 'meal_program_id');
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
