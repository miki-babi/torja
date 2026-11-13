<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A member's weekly check-in to their coach: weight, a note, and optional
 * photo/voice media. One per member per week (`user_id` + `week_of`
 * unique, `week_of` being the Monday). The trainer's reply lands back on
 * the row so the member sees it with their history.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $week_of
 * @property string|null $weight_kg
 * @property string|null $note
 * @property string|null $trainer_reply
 * @property int|null $replied_by
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'week_of', 'weight_kg', 'note', 'trainer_reply', 'replied_by', 'replied_at'])]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_of' => 'date',
            'weight_kg' => 'decimal:2',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * The Monday of the current check-in week.
     */
    public static function currentWeek(): CarbonInterface
    {
        return now()->startOfWeek();
    }

    /**
     * The member checking in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member who replied (the client's coach).
     *
     * @return BelongsTo<User, $this>
     */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Photos, videos and voice notes attached to this check-in.
     *
     * @return HasMany<CheckInMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(CheckInMedia::class);
    }

    /**
     * Whether the coach has replied yet.
     */
    public function needsReply(): bool
    {
        return $this->trainer_reply === null;
    }

    /**
     * Whether this check-in belongs to the current week.
     */
    public function isThisWeek(): bool
    {
        return $this->week_of->isSameDay(static::currentWeek());
    }
}
