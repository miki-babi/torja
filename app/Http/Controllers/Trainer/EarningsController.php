<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerEarning;
use App\Models\TrainerPayout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's money screen: this month's take, the platform commission,
 * the pending (unpaid) balance, and payout history with recent plan sales.
 */
class EarningsController extends Controller
{
    /**
     * Render the earnings dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        $monthGross = (float) $trainer->earnings()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('gross_amount');
        $monthCommission = (float) $trainer->earnings()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_amount');
        $pendingPayout = (float) $trainer->earnings()
            ->whereNull('payout_id')
            ->sum('net_amount');
        $paidClients = $trainer->plans()
            ->whereHas('clientPlans', fn ($query) => $query
                ->where('status', 'active')
                ->where('ends_at', '>', now()))
            ->count();

        return Inertia::render('trainer/earnings', [
            'summary' => [
                'month_gross' => $monthGross,
                'month_commission' => $monthCommission,
                'month_net' => $monthGross - $monthCommission,
                'pending_payout' => $pendingPayout,
                'paid_clients' => $paidClients,
                'commission_percent' => (float) config('platform.commission_percent'),
            ],
            'recentSales' => $trainer->earnings()
                ->with('clientPlan.user:id,name', 'clientPlan.plan:id,name')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (TrainerEarning $earning): array => [
                    'id' => $earning->id,
                    'client_name' => $earning->clientPlan?->user?->name,
                    'plan_name' => $earning->clientPlan?->plan?->name,
                    'gross_amount' => (float) $earning->gross_amount,
                    'net_amount' => (float) $earning->net_amount,
                    'settled' => $earning->payout_id !== null,
                    'created_at' => $earning->created_at,
                ]),
            'payouts' => $trainer->payouts()
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (TrainerPayout $payout): array => [
                    'id' => $payout->id,
                    'amount' => (float) $payout->amount,
                    'status' => $payout->status,
                    'sent_at' => $payout->sent_at,
                    'created_at' => $payout->created_at,
                ]),
        ]);
    }
}
