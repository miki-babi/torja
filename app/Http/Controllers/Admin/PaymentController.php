<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Payments\ChapaException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\ChapaService;
use App\Services\Payments\PaymentSynchronizer;
use App\Services\Referrals\ReferralCommissioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ChapaService $chapa,
        private readonly PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * Filterable, paginated list of all payment attempts.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $provider = trim((string) $request->query('provider', ''));

        return Inertia::render('admin/payments/index', [
            'payments' => Payment::query()
                ->with('user:id,name,email')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($provider !== '', fn ($query) => $query->where('provider', $provider))
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('tx_ref', 'like', "%{$search}%")
                        ->orWhere('deposit_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$search}%"))
                ))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search, 'status' => $status, 'provider' => $provider],
        ]);
    }

    /**
     * One payment with its owner and the raw provider response.
     */
    public function show(Payment $payment): Response
    {
        $payment->load('user:id,name,email');

        return Inertia::render('admin/payments/show', [
            'payment' => $payment,
        ]);
    }

    /**
     * Re-verify a payment against its provider and persist the result.
     */
    public function verify(Payment $payment): RedirectResponse
    {
        $this->synchronizer->sync($payment);

        Inertia::flash('toast', $payment->isSuccessful()
            ? ['type' => 'success', 'message' => __('Payment confirmed by :provider.', ['provider' => $payment->provider])]
            : ['type' => 'info', 'message' => __(':provider does not confirm this payment (status: :status).', ['provider' => $payment->provider, 'status' => $payment->status])]);

        return back();
    }

    /**
     * Refund a payment. Chapa supports an API refund (best-effort — it may
     * still need dashboard approval); Verify Checkout and Jami payments are
     * direct transfers with no refund API, so those are refunded by hand
     * and only marked refunded here.
     */
    public function refund(Payment $payment): RedirectResponse
    {
        if (! $payment->isSuccessful()) {
            return back()->withErrors([
                'refund' => __('Only successful payments can be refunded.'),
            ]);
        }

        if (! $payment->isChapa()) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Payment marked as refunded. Refund the buyer manually from your provider dashboard or wallet.')
                    .$this->clawbackCommissions($payment),
            ]);

            return back();
        }

        try {
            $response = $this->chapa->refund($payment->tx_ref);
        } catch (ChapaException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Refund request failed — you may need to refund from the Chapa dashboard.'),
            ]);

            return back();
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'provider_response' => $response,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment marked as refunded. If Chapa requires it, complete the refund from their dashboard.')
                .$this->clawbackCommissions($payment),
        ]);

        return back();
    }

    /**
     * Void unpaid ambassador commissions tied to a refunded payment.
     * Returns a warning suffix for the toast when an already-paid
     * commission exists — that money is out the door and needs a manual
     * follow-up with the ambassador.
     */
    private function clawbackCommissions(Payment $payment): string
    {
        $paid = app(ReferralCommissioner::class)->voidFor($payment);

        return $paid
            ? ' '.__('Note: a commission on this payment was already paid out.')
            : '';
    }
}
