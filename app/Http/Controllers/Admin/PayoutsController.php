<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff payout board: which trainers have unpaid earnings, the batch payouts
 * created for them, and marking a payout sent once money actually moved.
 */
class PayoutsController extends Controller
{
    public function index(): Response
    {
        $balances = Trainer::query()
            ->with('user:id,name,avatar_path')
            ->withCount(['earnings as unpaid_total' => fn ($query) => $query->whereNull('payout_id')->select(DB::raw('coalesce(sum(net_amount),0)'))])
            ->having('unpaid_total', '>', 0)
            ->get()
            ->map(fn (Trainer $trainer): array => [
                'id' => $trainer->id,
                'name' => $trainer->display_name,
                'avatar' => $trainer->user?->avatar,
                'payout_method' => $trainer->payout_method,
                'payout_account' => $trainer->payout_account,
                'unpaid_total' => (float) $trainer->unpaid_total,
            ]);

        $payouts = TrainerPayout::query()
            ->with(['trainer.user:id,name,avatar_path', 'processor:id,name'])
            ->withCount('earnings')
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/payouts/index', [
            'balances' => $balances,
            'payouts' => $payouts->through(fn (TrainerPayout $payout): array => [
                'id' => $payout->id,
                'trainer' => $payout->trainer->display_name,
                'avatar' => $payout->trainer->user?->avatar,
                'amount' => (float) $payout->amount,
                'status' => $payout->status,
                'earnings_count' => $payout->earnings_count,
                'processor' => $payout->processor?->name,
                'sent_at' => $payout->sent_at?->toDateTimeString(),
                'created_at' => $payout->created_at->toDateString(),
            ]),
        ]);
    }

    /**
     * Create a payout batch covering all of a trainer's unpaid earnings.
     */
    public function store(Trainer $trainer): RedirectResponse
    {
        $unpaid = $trainer->earnings()->whereNull('payout_id');

        $amount = (float) $unpaid->sum('net_amount');

        abort_if($amount <= 0, 422, 'No unpaid earnings.');

        DB::transaction(function () use ($trainer, $unpaid, $amount) {
            $payout = TrainerPayout::create([
                'trainer_id' => $trainer->id,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $unpaid->update(['payout_id' => $payout->id]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout of :amount ETB queued for :name.', ['amount' => number_format($amount), 'name' => $trainer->display_name])]);

        return back();
    }

    /**
     * Staff confirms the money moved — flips the payout to sent.
     */
    public function sent(TrainerPayout $payout): RedirectResponse
    {
        $payout->markSent(request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout marked sent.')]);

        return back();
    }
}
