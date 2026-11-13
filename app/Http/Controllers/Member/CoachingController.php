<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ClientPlan;
use App\Models\Payment;
use App\Models\TrainerPlan;
use App\Services\Payments\CheckoutLauncher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The member side of coaching plans: browsing one plan's checkout page,
 * paying for it through Jami, and viewing their own subscription status
 * with renewal shortcuts.
 */
class CoachingController extends Controller
{
    public function __construct(
        private readonly CheckoutLauncher $launcher,
    ) {}

    /**
     * The member's coaching subscriptions — the active plan (or latest
     * expired one) plus renewal history.
     */
    public function index(Request $request): Response
    {
        $plans = $request->user()->clientPlans()
            ->with('plan.trainer.user:id,name')
            ->latest('ends_at')
            ->get();

        $active = $plans->first(fn (ClientPlan $plan): bool => $plan->isActive());

        return Inertia::render('member/coaching', [
            'active' => $active === null ? null : [
                'id' => $active->id,
                'plan_name' => $active->plan->name,
                'trainer_name' => $active->plan->trainer->display_name,
                'starts_at' => $active->starts_at,
                'ends_at' => $active->ends_at,
                'days_left' => $active->daysLeft(),
                'due_soon' => $active->isDueSoon(),
                'renew_url' => route('member.plans.show', $active->trainer_plan_id),
            ],
            'history' => $plans->map(fn (ClientPlan $plan): array => [
                'id' => $plan->id,
                'plan_name' => $plan->plan->name,
                'trainer_name' => $plan->plan->trainer->display_name,
                'starts_at' => $plan->starts_at,
                'ends_at' => $plan->ends_at,
                'status' => $plan->isActive() ? 'active' : 'expired',
            ]),
        ]);
    }

    /**
     * The paywall page for one plan — guests are bounced to login and come
     * back here via the intended URL.
     */
    public function show(TrainerPlan $plan): Response
    {
        abort_unless($plan->is_active, 404);

        $plan->load('trainer.user:id,name');

        return Inertia::render('member/plan', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'kind_label' => TrainerPlan::KINDS[$plan->kind] ?? $plan->kind,
                'price_etb' => $plan->price_etb,
                'duration_days' => $plan->duration_days,
            ],
            'trainer' => [
                'name' => $plan->trainer->display_name,
                'slug' => $plan->trainer->slug,
            ],
        ]);
    }

    /**
     * Start a Jami checkout for the plan. The callback path reuses the
     * shared payment.* routes; fulfillment creates the ClientPlan via
     * Payment::markSuccessful.
     */
    public function checkout(Request $request, TrainerPlan $plan): HttpResponse|RedirectResponse
    {
        abort_unless($plan->is_active, 404);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', 'in:telebirr,mpesa,cbe'],
            'customer_phone' => ['required_with:gateway', 'nullable', 'string', 'min:9', 'max:16'],
        ]);

        $user = $request->user();

        abort_if($plan->trainer->user_id === $user->id, 403);

        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'jami',
            'tx_ref' => "tp-{$user->id}-".Str::uuid(),
            'idempotency_key' => 'checkout_'.Str::uuid(),
            'amount' => $plan->price_etb,
            'currency' => 'ETB',
            'status' => 'pending',
            'purpose' => 'plan_purchase',
            'payable_type' => $plan->getMorphClass(),
            'payable_id' => $plan->id,
            'email' => $user->email ?? "user-{$user->id}@trainpro.local",
            'first_name' => Str::before(trim($user->name).' ', ' '),
            'last_name' => trim(Str::after(trim($user->name).' ', ' ')) ?: null,
        ]);

        $launch = $this->launcher->start(
            $payment,
            $validated['gateway'] ?? null,
            $validated['customer_phone'] ?? null,
        );

        if ($launch->checkoutUrl !== null) {
            return Inertia::location($launch->checkoutUrl);
        }

        if ($launch->pushInitiated) {
            return to_route('member.coaching');
        }

        $payment->update(['status' => 'failed']);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('We could not start the payment. Please try again.'),
        ]);

        return to_route('member.plans.show', $plan);
    }
}
