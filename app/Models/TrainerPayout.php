<?php

namespace App\Models;

use Database\Factories\TrainerPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A manual payout batch to a trainer — created by staff covering all unpaid
 * earnings, then marked `sent` once the money moves (Telebirr/bank), which
 * is the confirmation the trainer sees.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $amount
 * @property string $status
 * @property string|null $note
 * @property int|null $processed_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'amount', 'status', 'note', 'processed_by', 'sent_at'])]
class TrainerPayout extends Model
{
    /** @use HasFactory<TrainerPayoutFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The coaching tenant being paid.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * The staff user who marked the payout sent.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * The earnings this payout settles.
     *
     * @return HasMany<TrainerEarning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(TrainerEarning::class, 'payout_id');
    }

    /**
     * Mark the payout as sent and stamp the covering staff user. Pending
     * rows stay visible to the trainer as "payout on the way".
     */
    public function markSent(User $staff): void
    {
        if ($this->status !== 'pending') {
            return;
        }

        $this->update([
            'status' => 'sent',
            'processed_by' => $staff->id,
            'sent_at' => now(),
        ]);
    }
}
