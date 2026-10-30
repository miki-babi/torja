<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentSynchronizer;
use App\Support\ProPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProController extends Controller
{
    public function __construct(
        private readonly PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * The upgrade page: Pro benefits, the one-time price and a checkout
     * button per configured provider. `pendingJamiPayment` drives the
     * full-screen overlay after a direct push or an abandoned payment
     * link; the phone is masked before it reaches the browser.
     */
    public function show(Request $request): Response
    {
        $pending = $request->user()->payments()
            ->where('provider', 'jami')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pending !== null) {
            $this->synchronizer->sync($pending);
        }

        return Inertia::render('pro', [
            'isPro' => $request->user()->isPro(),
            'price' => ProPage::price(),
            'page' => ProPage::forMember(),
            'homeUrl' => route('member.home'),
            'providers' => [
                'chapa' => filled(config('services.chapa.secret_key')),
                'verify_checkout' => filled(config('services.verify_checkout.api_key')),
                'jami' => filled(config('services.jami.token')),
            ],
            'pendingJamiPayment' => $pending?->fresh()->status === 'pending'
                ? [
                    'id' => $pending->id,
                    'amount' => (float) $pending->amount,
                    'currency' => $pending->currency,
                    'gateway' => data_get($pending->provider_response, 'direct.gateway'),
                    'phone' => $this->maskedPhone(data_get($pending->provider_response, 'direct.phone')),
                    'checkoutUrl' => data_get($pending->provider_response, 'checkoutUrl'),
                ]
                : null,
            'checkoutUrl' => route('plan.checkout'),
        ]);
    }

    /**
     * Keep the first 4 and last 3 digits, mask the middle — enough for the
     * buyer to recognise the number without exposing it.
     */
    private function maskedPhone(?string $phone): ?string
    {
        if (! is_string($phone) || strlen($phone) <= 7) {
            return $phone;
        }

        return substr($phone, 0, 4).str_repeat('•', strlen($phone) - 7).substr($phone, -3);
    }
}
