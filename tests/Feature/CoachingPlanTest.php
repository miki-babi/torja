<?php

use App\Models\ClientPlan;
use App\Models\Payment;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use App\Models\TrainerPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('a trainer creates, updates and toggles a coaching plan', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainerUser->ensureTrainer();

    $this->actingAs($trainerUser)
        ->post(route('trainer.plans.store'), [
            'name' => 'Monthly 1:1',
            'description' => 'Weekly check-ins.',
            'kind' => 'one_on_one',
            'price_etb' => 2000,
            'duration_days' => 30,
        ])
        ->assertRedirect(route('trainer.plans.index'));

    $plan = TrainerPlan::sole();
    expect($plan->trainer_id)->toBe($trainerUser->trainer->id)
        ->and($plan->is_active)->toBeTrue();

    $this->post(route('trainer.plans.toggle', $plan));
    expect($plan->fresh()->is_active)->toBeFalse();

    $this->put(route('trainer.plans.update', $plan), [
        'name' => 'Renamed',
        'kind' => 'check_in',
        'price_etb' => 1500,
        'duration_days' => 14,
    ]);
    expect($plan->fresh()->name)->toBe('Renamed');
});

test('a trainer cannot touch another trainer\'s plan', function () {
    $plan = TrainerPlan::factory()->create();
    $other = User::factory()->trainer()->create();
    $other->ensureTrainer();

    $this->actingAs($other)
        ->put(route('trainer.plans.update', $plan), [
            'name' => 'Hijack',
            'kind' => 'group',
            'price_etb' => 10,
            'duration_days' => 10,
        ])
        ->assertForbidden();
});

test('a coachless member can view a plan, pay, and becomes a client', function () {
    $trainer = Trainer::factory()->create();
    $plan = TrainerPlan::factory()->for($trainer)->create([
        'price_etb' => 2000,
        'duration_days' => 30,
    ]);
    $member = User::factory()->create();

    // The member has no coach — the plan pages are exempt from the gate.
    $this->actingAs($member)
        ->get(route('member.plans.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.price_etb', 2000)
            ->where('trainer.slug', $trainer->slug));

    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'sessionId' => 'cs_plan',
            'mode' => 'redirect',
            'checkoutUrl' => 'https://jami.bio/jamidev/pay/cs_plan',
        ]),
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => 'cs_plan',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    $this->post(route('member.plans.checkout', $plan), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://jami.bio/jamidev/pay/cs_plan');

    $payment = Payment::sole();
    expect($payment->purpose)->toBe('plan_purchase')
        ->and($payment->payable->is($plan))->toBeTrue();

    $this->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('member.coaching'));

    $clientPlan = ClientPlan::sole();
    expect($clientPlan->user_id)->toBe($member->id)
        ->and($clientPlan->starts_at->diffInDays($clientPlan->ends_at))->toBe(30.0);

    // 5% platform commission.
    $earning = TrainerEarning::sole();
    expect((float) $earning->gross_amount)->toBe(2000.0)
        ->and((float) $earning->commission_amount)->toBe(100.0)
        ->and((float) $earning->net_amount)->toBe(1900.0);

    // Paying linked the member to the trainer's roster.
    expect($member->fresh()->isClientOf($trainer->user))->toBeTrue();

    $this->get(route('member.coaching'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('active.plan_name', $plan->name)
            ->where('active.days_left', 30));
});

test('renewing while active stacks the new period on the current end date', function () {
    $plan = TrainerPlan::factory()->create(['duration_days' => 30]);
    $member = User::factory()->create();

    $existing = ClientPlan::factory()->for($plan, 'plan')->for($member)->create([
        'ends_at' => now()->addDays(10),
    ]);

    $payment = Payment::factory()->for($member)->create([
        'status' => 'pending',
        'purpose' => 'plan_purchase',
        'payable_type' => $plan->getMorphClass(),
        'payable_id' => $plan->id,
        'amount' => $plan->price_etb,
    ]);

    $payment->markSuccessful();

    $renewal = ClientPlan::query()->latest('id')->first();
    expect($renewal->starts_at->toDateTimeString())
        ->toBe($existing->ends_at->toDateTimeString())
        ->and($renewal->ends_at->toDateTimeString())
        ->toBe($existing->ends_at->copy()->addDays(30)->toDateTimeString());
});

test('the earnings screen shows the pending payout and month totals', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();
    $plan = TrainerPlan::factory()->for($trainer)->create();

    $member = User::factory()->create();
    $payment = Payment::factory()->for($member)->create([
        'status' => 'pending',
        'purpose' => 'plan_purchase',
        'payable_type' => $plan->getMorphClass(),
        'payable_id' => $plan->id,
        'amount' => 2000,
    ]);
    $payment->markSuccessful();

    $this->actingAs($trainerUser)
        ->get(route('trainer.earnings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.month_gross', 2000)
            ->where('summary.pending_payout', 1900)
            ->where('summary.paid_clients', 1)
            ->has('recentSales', 1));
});

test('inactive plans cannot be bought or viewed', function () {
    $plan = TrainerPlan::factory()->inactive()->create();
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('member.plans.show', $plan))
        ->assertNotFound();

    $this->post(route('member.plans.checkout', $plan))
        ->assertNotFound();
});
