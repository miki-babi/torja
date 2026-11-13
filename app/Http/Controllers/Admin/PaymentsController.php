<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only ledger of every checkout attempt on the platform — staff can
 * filter by status and see who bought what for how much.
 */
class PaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $payments = Payment::query()
            ->with(['payable', 'user:id,name,email'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('tx_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($users) => $users
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/payments/index', [
            'payments' => $payments->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'reference' => $payment->tx_ref,
                'user' => $payment->user?->name ?? $payment->email,
                'item' => $payment->payable?->name ?? $payment->payable?->localizedName() ?? class_basename($payment->payable_type),
                'purpose' => $payment->purpose,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'created_at' => $payment->created_at->toDateString(),
            ]),
            'filters' => ['status' => $status, 'search' => $search],
            'totals' => [
                'success' => (float) Payment::where('status', 'success')->sum('amount'),
                'pending' => Payment::where('status', 'pending')->count(),
                'failed' => Payment::where('status', 'failed')->count(),
            ],
        ]);
    }
}
