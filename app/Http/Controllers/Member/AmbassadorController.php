<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Support\AmbassadorProgram;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AmbassadorController extends Controller
{
    /**
     * The ambassador wallet: invite link, balances, a merged credit/debit
     * ledger, referral list and payout history.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $user->ensureReferralCode();

        $commissions = $user->referralCommissions()
            ->with('referredUser:id,name')
            ->latest()
            ->get();

        $payouts = $user->referralPayouts()->latest()->get();

        /** @var Collection<int, User> $referrals */
        $referrals = $user->referrals()->latest()->limit(15)->get();

        $proIds = Payment::query()
            ->whereIn('user_id', $referrals->modelKeys())
            ->where('status', 'success')
            ->where('purpose', 'pro')
            ->pluck('user_id')
            ->flip();

        $commissionByReferred = $commissions->keyBy('referred_user_id');

        return Inertia::render('member/ambassador', [
            'enabled' => AmbassadorProgram::isEnabled(),
            'inviteUrl' => $user->inviteUrl(),
            'commissionEtb' => AmbassadorProgram::commissionEtb(),
            'minPayoutEtb' => AmbassadorProgram::minPayoutEtb(),
            'wallet' => [
                'available' => (float) $commissions
                    ->where('status', 'approved')->where('payout_id', null)->sum('amount'),
                'pending' => (float) $commissions->where('status', 'pending')->sum('amount'),
                'paid' => (float) $commissions->where('status', 'paid')->sum('amount'),
            ],
            'stats' => [
                'referrals' => $user->referrals()->count(),
                'conversions' => $commissions->whereIn('status', ['approved', 'paid'])->count(),
            ],
            'ledger' => $commissions
                ->map(fn ($commission) => [
                    'id' => "c{$commission->id}",
                    'kind' => 'credit',
                    'amount' => (float) $commission->amount,
                    'status' => $commission->status,
                    'label' => $commission->referredUser->name ?? __('A friend'),
                    'date' => $commission->created_at->toDateString(),
                ])
                ->concat($payouts->map(fn ($payout) => [
                    'id' => "p{$payout->id}",
                    'kind' => 'debit',
                    'amount' => (float) $payout->amount,
                    'status' => $payout->status,
                    'label' => $payout->method,
                    'date' => $payout->created_at->toDateString(),
                ]))
                ->sortByDesc('date')
                ->values()
                ->take(30),
            'referrals' => $referrals->map(fn ($referral) => [
                'name' => $referral->name,
                'joined' => $referral->created_at->toDateString(),
                'is_pro' => $proIds->has($referral->id),
                'commission_status' => $commissionByReferred->get($referral->id)?->status,
            ])->values(),
            'payouts' => $payouts->map(fn ($payout) => [
                'id' => $payout->id,
                'amount' => (float) $payout->amount,
                'method' => $payout->method,
                'status' => $payout->status,
                'date' => $payout->created_at->toDateString(),
            ])->values(),
            'hasOpenPayout' => $payouts->contains(fn ($payout) => $payout->isRequested()),
        ]);
    }

    /**
     * Request a payout for the full approved balance. The covered
     * commissions are locked to the payout (payout_id) until an admin
     * settles or rejects it.
     */
    public function storePayout(Request $request): RedirectResponse
    {
        abort_unless(AmbassadorProgram::isEnabled(), 404);

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(['telebirr', 'mpesa', 'cbe', 'bank'])],
            'account' => ['required', 'string', 'min:5', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();

        if ($user->referralPayouts()->where('status', 'requested')->exists()) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('You already have a payout request in review.'),
            ]);

            return back();
        }

        $commissions = $user->referralCommissions()
            ->where('status', 'approved')
            ->whereNull('payout_id')
            ->get();

        $balance = (float) $commissions->sum('amount');
        $minimum = AmbassadorProgram::minPayoutEtb();

        if ($balance < $minimum) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You need at least :amount ETB to request a payout.', [
                    'amount' => number_format($minimum),
                ]),
            ]);

            return back();
        }

        $payout = ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => $balance,
            'method' => $validated['method'],
            'account' => $validated['account'],
            'note' => $validated['note'] ?? null,
            'status' => 'requested',
        ]);

        $commissions->each->update(['payout_id' => $payout->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payout requested — we will send it shortly.'),
        ]);

        return back();
    }
}
