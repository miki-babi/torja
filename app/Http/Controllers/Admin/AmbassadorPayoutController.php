<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AmbassadorPayoutController extends Controller
{
    /**
     * Settle a payout request after the money was sent manually — marks
     * the payout paid and stamps every covered commission.
     */
    public function markPaid(Request $request, ReferralPayout $payout): RedirectResponse
    {
        $payout->markPaid($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payout marked as paid.'),
        ]);

        return back();
    }

    /**
     * Reject a payout request — its commissions return to the
     * ambassador's approved balance for a future request.
     */
    public function reject(Request $request, ReferralPayout $payout): RedirectResponse
    {
        $payout->reject($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payout rejected — the balance returned to the ambassador.'),
        ]);

        return back();
    }
}
