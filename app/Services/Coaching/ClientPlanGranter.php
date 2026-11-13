<?php

namespace App\Services\Coaching;

use App\Models\ClientPlan;
use App\Models\Payment;
use App\Models\TrainerPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fulfills a successful coaching-plan payment: starts (or extends) the
 * client's subscription, links them to the trainer's roster, and records
 * the trainer's earning net of platform commission. Idempotent per
 * payment — the earning's unique payment_id constraint makes a second
 * call a no-op.
 */
class ClientPlanGranter
{
    /**
     * Start or extend the subscription and record the earning. Renewals
     * stack onto an active subscription's end date rather than resetting
     * it, so early renewals don't lose paid days.
     */
    public function grant(User $user, TrainerPlan $plan, Payment $payment): ClientPlan
    {
        return DB::transaction(function () use ($user, $plan, $payment): ClientPlan {
            $plan->trainer->clients()->syncWithoutDetaching([
                $user->id => ['last_opened_at' => now()],
            ]);

            $latest = $plan->clientPlans()
                ->where('user_id', $user->id)
                ->orderByDesc('ends_at')
                ->first();

            $startsAt = $latest?->isActive() ? $latest->ends_at : now();

            $clientPlan = ClientPlan::create([
                'trainer_plan_id' => $plan->id,
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays($plan->duration_days),
                'status' => 'active',
            ]);

            $percent = (float) config('platform.commission_percent');
            $gross = (float) $payment->amount;

            $plan->trainer->earnings()->firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'client_plan_id' => $clientPlan->id,
                    'gross_amount' => $gross,
                    'commission_amount' => round($gross * $percent / 100, 2),
                    'net_amount' => round($gross * (100 - $percent) / 100, 2),
                ],
            );

            return $clientPlan;
        });
    }
}
