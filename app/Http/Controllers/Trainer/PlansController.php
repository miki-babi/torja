<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trainer-managed coaching plans — the priced, time-boxed offers listed on
 * the trainer's public /t/{slug} page. Clients buy them through the member
 * checkout flow, which grants a ClientPlan subscription.
 */
class PlansController extends Controller
{
    /**
     * List the trainer's plans with subscriber counts.
     */
    public function index(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/plans/index', [
            'plans' => $trainer->plans()
                ->withCount('clientPlans')
                ->latest()
                ->get()
                ->map(fn (TrainerPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'kind' => $plan->kind,
                    'kind_label' => TrainerPlan::KINDS[$plan->kind] ?? $plan->kind,
                    'price_etb' => $plan->price_etb,
                    'duration_days' => $plan->duration_days,
                    'is_active' => $plan->is_active,
                    'client_plans_count' => $plan->client_plans_count,
                ]),
            'kinds' => TrainerPlan::KINDS,
        ]);
    }

    /**
     * Blank plan form.
     */
    public function create(): Response
    {
        return Inertia::render('trainer/plans/edit', [
            'plan' => null,
            'kinds' => TrainerPlan::KINDS,
        ]);
    }

    /**
     * Create a plan.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->user()->ensureTrainer()->plans()->create($this->validated($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Plan created — it is live on your public page.'),
        ]);

        return to_route('trainer.plans.index');
    }

    /**
     * Edit form for one of the trainer's plans.
     */
    public function edit(Request $request, TrainerPlan $plan): Response
    {
        $this->authorizePlan($request, $plan);

        return Inertia::render('trainer/plans/edit', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'kind' => $plan->kind,
                'price_etb' => $plan->price_etb,
                'duration_days' => $plan->duration_days,
                'is_active' => $plan->is_active,
            ],
            'kinds' => TrainerPlan::KINDS,
        ]);
    }

    /**
     * Update a plan. Price/duration changes only affect future purchases —
     * existing subscriptions keep what they bought.
     */
    public function update(Request $request, TrainerPlan $plan): RedirectResponse
    {
        $this->authorizePlan($request, $plan);

        $plan->update($this->validated($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Plan updated.'),
        ]);

        return to_route('trainer.plans.index');
    }

    /**
     * Toggle whether the plan shows on the public page.
     */
    public function toggle(Request $request, TrainerPlan $plan): RedirectResponse
    {
        $this->authorizePlan($request, $plan);

        $plan->update(['is_active' => ! $plan->is_active]);

        return back();
    }

    /**
     * Remove a plan with no subscribers.
     */
    public function destroy(Request $request, TrainerPlan $plan): RedirectResponse
    {
        $this->authorizePlan($request, $plan);
        abort_if($plan->clientPlans()->exists(), 422, 'Plans with subscribers cannot be deleted.');

        $plan->delete();

        return to_route('trainer.plans.index');
    }

    /**
     * Only the owning trainer may touch a plan.
     */
    private function authorizePlan(Request $request, TrainerPlan $plan): void
    {
        abort_if($plan->trainer_id !== $request->user()->ensureTrainer()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(array_keys(TrainerPlan::KINDS))],
            'price_etb' => ['required', 'integer', 'min:1', 'max:1000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3660'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
