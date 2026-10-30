<?php

namespace App\Services\Referrals;

use App\Models\Payment;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Support\AmbassadorProgram;
use App\Support\Tracking\Tracker;

/**
 * Creates and voids ambassador commissions. Commissions are created
 * `pending` (admins approve them before they become payable wallet
 * balance) and voided when the underlying payment is refunded.
 */
class ReferralCommissioner
{
    /**
     * Credit the referrer of a successful Pro payment with a pending
     * commission. Idempotent via the unique `payment_id` — repeat
     * `markSuccessful` calls (webhook + callback) create at most one row.
     */
    public function grant(Payment $payment): void
    {
        if (! AmbassadorProgram::isEnabled() || $payment->purpose !== 'pro') {
            return;
        }

        $buyer = $payment->user;
        $referrer = $buyer?->referred_by_id === null
            ? null
            : User::query()->find($buyer->referred_by_id);

        if ($buyer === null || $referrer === null || $referrer->is($buyer)) {
            return;
        }

        $amount = AmbassadorProgram::commissionEtb();

        if ($amount <= 0) {
            return;
        }

        $commission = ReferralCommission::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'affiliate_user_id' => $referrer->id,
                'referred_user_id' => $buyer->id,
                'amount' => $amount,
                'status' => 'pending',
            ],
        );

        if ($commission->wasRecentlyCreated) {
            app(Tracker::class)->record('ambassador.commission_pending', $referrer, [
                'commission_id' => $commission->id,
                'amount' => (float) $commission->amount,
                'referred_user_id' => $buyer->id,
                'payment_id' => $payment->id,
            ], $commission);
        }
    }

    /**
     * Void unpaid commissions tied to a refunded payment. Returns whether
     * an already-paid commission exists — those are real money out the
     * door and need a manual conversation, not an automatic clawback.
     */
    public function voidFor(Payment $payment): bool
    {
        $commissions = ReferralCommission::query()
            ->where('payment_id', $payment->id)
            ->get();

        $paid = false;

        foreach ($commissions as $commission) {
            if ($commission->status === 'paid') {
                $paid = true;

                continue;
            }

            $commission->void('Payment refunded');
        }

        return $paid;
    }
}
