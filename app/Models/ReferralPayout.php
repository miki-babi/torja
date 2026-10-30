<?php

namespace App\Models;

use Database\Factories\ReferralPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An ambassador's cash-out request for their approved commission balance.
 * `account`/`method` describe where the admin should send the money (e.g.
 * a Telebirr phone number). Requested payouts lock their commissions via
 * `payout_id`; marking paid settles them, rejecting releases them back to
 * the ambassador's balance.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $amount
 * @property string $method
 * @property string $account
 * @property string|null $note
 * @property string $status
 * @property int|null $processed_by_id
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'amount', 'method', 'account', 'note', 'status', 'processed_by_id', 'processed_at'])]
class ReferralPayout extends Model
{
    /** @use HasFactory<ReferralPayoutFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The ambassador cashing out.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who settled or rejected the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    /**
     * The commissions this payout covers.
     *
     * @return HasMany<ReferralCommission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'payout_id');
    }

    /**
     * Whether the request is still awaiting admin settlement.
     */
    public function isRequested(): bool
    {
        return $this->status === 'requested';
    }

    /**
     * Settle the payout: mark it paid and stamp every covered commission.
     */
    public function markPaid(User $admin): void
    {
        if (! $this->isRequested()) {
            return;
        }

        $this->update([
            'status' => 'paid',
            'processed_by_id' => $admin->id,
            'processed_at' => now(),
        ]);

        $this->commissions()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Reject the request: release its commissions back to the ambassador's
     * approved balance so they can be included in a future payout.
     */
    public function reject(User $admin): void
    {
        if (! $this->isRequested()) {
            return;
        }

        $this->commissions()->update(['payout_id' => null]);

        $this->update([
            'status' => 'rejected',
            'processed_by_id' => $admin->id,
            'processed_at' => now(),
        ]);
    }
}
