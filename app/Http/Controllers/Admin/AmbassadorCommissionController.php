<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AmbassadorCommissionController extends Controller
{
    /**
     * Approve a pending commission so it counts toward the ambassador's
     * payable balance.
     */
    public function approve(Request $request, ReferralCommission $commission): RedirectResponse
    {
        $commission->approve($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Commission approved.'),
        ]);

        return back();
    }

    /**
     * Void a pending or approved commission — fraud, abuse or a manual
     * correction. Paid commissions are real money out the door and are
     * left alone.
     */
    public function void(Request $request, ReferralCommission $commission): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $commission->void($validated['reason'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Commission voided.'),
        ]);

        return back();
    }
}
