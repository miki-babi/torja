<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Models\Setting;
use App\Support\AmbassadorProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmbassadorController extends Controller
{
    /**
     * Ambassador overview: headline stats, the pending-approval queue, a
     * filterable commission ledger and the payout request queue.
     */
    public function index(Request $request): Response
    {
        $status = trim((string) $request->query('status', ''));

        return Inertia::render('admin/ambassadors/index', [
            'stats' => [
                'pending' => ReferralCommission::query()->where('status', 'pending')->count(),
                'approved_unpaid' => (float) ReferralCommission::query()
                    ->where('status', 'approved')->sum('amount'),
                'requested_payouts' => ReferralPayout::query()->where('status', 'requested')->count(),
                'paid_total' => (float) ReferralCommission::query()
                    ->where('status', 'paid')->sum('amount'),
            ],
            'pendingCommissions' => ReferralCommission::query()
                ->with([
                    'affiliate:id,name,phone,email',
                    'referredUser:id,name',
                    'payment:id,amount',
                ])
                ->where('status', 'pending')
                ->latest()
                ->limit(20)
                ->get(),
            'commissions' => ReferralCommission::query()
                ->with([
                    'affiliate:id,name',
                    'referredUser:id,name',
                    'payment:id,amount',
                ])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'payoutRequests' => ReferralPayout::query()
                ->with('user:id,name,phone,email')
                ->where('status', 'requested')
                ->latest()
                ->limit(20)
                ->get(),
            'recentPayouts' => ReferralPayout::query()
                ->with('user:id,name')
                ->where('status', '!=', 'requested')
                ->latest()
                ->limit(10)
                ->get(),
            'filters' => ['status' => $status],
        ]);
    }

    /**
     * The ambassador settings form.
     */
    public function editSettings(): Response
    {
        return Inertia::render('admin/ambassadors/settings', [
            'settings' => AmbassadorProgram::settings(),
            'defaults' => AmbassadorProgram::defaults(),
        ]);
    }

    /**
     * Save the ambassador settings payload as one setting.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(AmbassadorProgram::rules());

        Setting::set(AmbassadorProgram::SETTING_KEY, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ambassador settings saved.'),
        ]);

        return back();
    }
}
