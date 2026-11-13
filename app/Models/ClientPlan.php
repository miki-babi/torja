<?php

namespace App\Models;

use Database\Factories\ClientPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's subscription to a trainer's coaching plan — one row per
 * purchase/renewal. `ends_at` is the expiry both sides see; `status` flips
 * to `expired` when it lapses (or is marked when queried).
 *
 * @property int $id
 * @property int $trainer_plan_id
 * @property int $user_id
 * @property int|null $payment_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_plan_id', 'user_id', 'payment_id', 'starts_at', 'ends_at', 'status'])]
class ClientPlan extends Model
{
    /** @use HasFactory<ClientPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The purchased plan.
     *
     * @return BelongsTo<TrainerPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainerPlan::class, 'trainer_plan_id');
    }

    /**
     * The subscribed client.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The payment that bought this subscription.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Whether the subscription is live right now.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    /**
     * Whether the subscription has run out — lapsed by time or cancelled.
     */
    public function isExpired(): bool
    {
        return $this->status !== 'active' || $this->ends_at->isPast();
    }

    /**
     * Whether the plan ends soon enough to nudge a renewal.
     */
    public function isDueSoon(): bool
    {
        return $this->isActive() && $this->ends_at->lte(now()->addDays(7));
    }

    /**
     * Whole days left on the subscription (0 once expired).
     */
    public function daysLeft(): int
    {
        return max(0, (int) round(now()->diffInDays($this->ends_at)));
    }
}
