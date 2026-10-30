<?php

namespace App\Models;

use Database\Factories\ReferralCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A commission earned by an ambassador (`affiliate_user_id`) when a user
 * they referred (`referred_user_id`) pays for Pro (`payment_id`). Created
 * `pending` for admin review, `approved` becomes payable wallet balance,
 * `paid` is settled via a ReferralPayout, `void` is rejected or clawed
 * back after a refund. `amount` is the ETB snapshot at creation — later
 * changes to the ambassador settings don't alter existing rows.
 *
 * @property int $id
 * @property int|null $affiliate_user_id
 * @property int|null $referred_user_id
 * @property int|null $payment_id
 * @property int|null $payout_id
 * @property string $amount
 * @property string $status
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['affiliate_user_id', 'referred_user_id', 'payment_id', 'payout_id', 'amount', 'status', 'approved_by_id', 'approved_at', 'paid_at', 'voided_at', 'void_reason'])]
class ReferralCommission extends Model
{
    /** @use HasFactory<ReferralCommissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * The ambassador who earned this commission.
     *
     * @return BelongsTo<User, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    /**
     * The referred friend whose Pro payment earned the commission.
     *
     * @return BelongsTo<User, $this>
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The payout that settled this commission, once paid.
     *
     * @return BelongsTo<ReferralPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(ReferralPayout::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Whether the commission awaits admin review.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Whether the commission counts toward the ambassador's payable balance.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Whether the commission is still attached to an open payout request.
     */
    public function isPayoutLocked(): bool
    {
        return $this->payout_id !== null && $this->status === 'approved';
    }

    /**
     * Approve the commission so it counts toward the ambassador's balance.
     */
    public function approve(User $admin): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => 'approved',
            'approved_by_id' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Void a pending or approved commission — rejected by an admin or
     * clawed back when the underlying payment is refunded.
     */
    public function void(?string $reason = null): void
    {
        if (! in_array($this->status, ['pending', 'approved'], true)) {
            return;
        }

        $this->update([
            'status' => 'void',
            'payout_id' => null,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
    }
}
