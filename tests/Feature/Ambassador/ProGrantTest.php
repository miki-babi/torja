<?php

use App\Listeners\BindReferrer;
use App\Models\Payment;
use App\Models\ProGrant;
use App\Models\ReferralCommission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('binding the nth referral grants the ambassador free pro', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 2]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();
    User::factory()->create(['referred_by_id' => $ambassador->id]);

    expect($ambassador->isPro())->toBeFalse();

    $friend = User::factory()->create();
    app(BindReferrer::class)->bind($friend, $ambassador->referral_code);

    $grant = ProGrant::sole();

    expect($grant->user_id)->toBe($ambassador->id)
        ->and($grant->source)->toBe('referral_reward')
        ->and($grant->referral_count)->toBe(2)
        ->and($ambassador->fresh()->isPro())->toBeTrue()
        ->and($ambassador->fresh()->proType())->toBe('granted');
});

test('referrals below the threshold grant nothing', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 3]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    expect(ProGrant::count())->toBe(0)
        ->and($ambassador->fresh()->isPro())->toBeFalse();
});

test('a paid member crossing the threshold gets no grant', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 1]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();
    Payment::factory()->for($ambassador)->successful()->create();

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    expect(ProGrant::count())->toBe(0)
        ->and($ambassador->fresh()->proType())->toBe('paid');
});

test('a zero threshold or disabled program never grants pro', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 0]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    Setting::set('ambassador', ['enabled' => false, 'referrals_for_pro' => 1]);

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    expect(ProGrant::count())->toBe(0);
});

test('granted pro is idempotent and survives extra referrals', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 1]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);
    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    expect(ProGrant::query()->active()->count())->toBe(1);
});

test('granted pro creates no payment and no affiliate commission', function () {
    Setting::set('ambassador', ['enabled' => true, 'referrals_for_pro' => 1]);

    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    app(BindReferrer::class)->bind(User::factory()->create(), $ambassador->referral_code);

    expect($ambassador->fresh()->isPro())->toBeTrue()
        ->and(Payment::count())->toBe(0)
        ->and(ReferralCommission::count())->toBe(0);
});

test('a granted member who buys pro becomes paid and earns their referrer commission', function () {
    $ambassador = User::factory()->create();
    $member = User::factory()->create(['referred_by_id' => $ambassador->id]);
    ProGrant::factory()->for($member)->create();

    $payment = Payment::factory()->for($member)->create();
    $payment->markSuccessful();

    expect($member->fresh()->proType())->toBe('paid')
        ->and(ReferralCommission::sole()->referred_user_id)->toBe($member->id);
});

test('a granted member can still start a paid checkout', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'sessionId' => 'cs_test123',
            'mode' => 'redirect',
            'checkoutUrl' => 'https://jami.bio/jamidev/pay/cs_test123',
        ]),
    ]);

    $user = User::factory()->create();
    ProGrant::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('plan.checkout'));

    $response->assertStatus(409);

    expect(Payment::sole()->user_id)->toBe($user->id);
});
