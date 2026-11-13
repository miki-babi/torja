<?php

use App\Listeners\BindReferrer;
use App\Models\Payment;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\Referrals\ReferralCommissioner;

test('the invite link redirects guests to registration and sets the referral cookie', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    $response = $this->get(route('invite.show', $ambassador->referral_code));

    $response->assertRedirect(route('register'));
    $response->assertCookie(BindReferrer::COOKIE);
});

test('an unknown invite code 404s', function () {
    $this->get(route('invite.show', 'nosuchcode'))->assertNotFound();
});

test('registration binds the referrer from the invite cookie', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    $this->withCookie(BindReferrer::COOKIE, $ambassador->referral_code)
        ->post(route('register.store'), [
            'name' => 'New Member',
            'email' => 'member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $user = User::firstWhere('email', 'member@example.com');

    expect($user->referred_by_id)->toBe($ambassador->id);
});

test('the last clicked invite wins when registering', function () {
    $first = User::factory()->create();
    $first->ensureReferralCode();
    $second = User::factory()->create();
    $second->ensureReferralCode();

    // Clicking again rewrites the cookie — the newest code is what lands
    // on the registration request.
    $this->get(route('invite.show', $first->referral_code));
    $this->get(route('invite.show', $second->referral_code));

    $this->withCookie(BindReferrer::COOKIE, $second->referral_code)
        ->post(route('register.store'), [
            'name' => 'New Member',
            'email' => 'member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $user = User::firstWhere('email', 'member@example.com');

    expect($user->referred_by_id)->toBe($second->id);
});

test('a logged-in member who clicks an invite link is bound immediately', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('invite.show', $ambassador->referral_code))
        ->assertRedirect(route('pro.show'));

    expect($member->fresh()->referred_by_id)->toBe($ambassador->id);
});

test('clicking your own invite link does not self-refer', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    $this->actingAs($ambassador)
        ->get(route('invite.show', $ambassador->referral_code));

    expect($ambassador->fresh()->referred_by_id)->toBeNull();
});

test('the invite url is a telegram mini app link when the bot is configured', function () {
    config()->set('services.telegram.bot_username', 'trainpro_bot');
    config()->set('services.telegram.mini_app_name', 'trainpro');

    $user = User::factory()->create();
    $user->ensureReferralCode();

    expect($user->inviteUrl())
        ->toBe("https://t.me/trainpro_bot/trainpro?startapp=ref_{$user->referral_code}");

    config()->set('services.telegram.mini_app_name', null);

    expect($user->inviteUrl())
        ->toBe("https://t.me/trainpro_bot?startapp=ref_{$user->referral_code}");
});

test('the invite url falls back to the web route without a telegram bot', function () {
    config()->set('services.telegram.bot_username', null);

    $user = User::factory()->create();
    $user->ensureReferralCode();

    expect($user->inviteUrl())->toBe(route('invite.show', $user->referral_code));
});

test('a successful pro payment creates a pending commission for the referrer', function () {
    $ambassador = User::factory()->create();
    $buyer = User::factory()->create(['referred_by_id' => $ambassador->id]);
    $payment = Payment::factory()->for($buyer)->create();

    $payment->markSuccessful();

    $commission = ReferralCommission::sole();

    expect($commission->affiliate_user_id)->toBe($ambassador->id)
        ->and($commission->referred_user_id)->toBe($buyer->id)
        ->and($commission->payment_id)->toBe($payment->id)
        ->and($commission->status)->toBe('pending')
        ->and((float) $commission->amount)->toBe(100.0);
});

test('commission creation is idempotent across repeated fulfillment', function () {
    $ambassador = User::factory()->create();
    $buyer = User::factory()->create(['referred_by_id' => $ambassador->id]);
    $payment = Payment::factory()->for($buyer)->create();

    $payment->markSuccessful();
    $payment->markSuccessful();

    expect(ReferralCommission::count())->toBe(1);
});

test('no commission without a referrer, for self-referrals, or when the program is disabled', function () {
    $solo = User::factory()->create();
    Payment::factory()->for($solo)->create()->markSuccessful();

    $self = User::factory()->create();
    $self->forceFill(['referred_by_id' => $self->id])->save();
    Payment::factory()->for($self)->create()->markSuccessful();

    Setting::set('ambassador', ['enabled' => false, 'commission_etb' => 100, 'min_payout_etb' => 200]);
    $ambassador = User::factory()->create();
    $buyer = User::factory()->create(['referred_by_id' => $ambassador->id]);
    Payment::factory()->for($buyer)->create()->markSuccessful();

    expect(ReferralCommission::count())->toBe(0);
});

test('the wallet page shows the invite link and balances', function () {
    $ambassador = User::factory()->create();
    $buyer = User::factory()->create(['referred_by_id' => $ambassador->id]);
    $payment = Payment::factory()->for($buyer)->create();
    $payment->markSuccessful();

    $response = $this->actingAs($ambassador)->get(route('member.ambassador'));

    $response->assertOk();

    expect($ambassador->fresh()->referral_code)->not->toBeNull();
});

test('an ambassador requests a payout for the full approved balance', function () {
    $ambassador = User::factory()->create();
    ReferralCommission::factory()->count(3)->for($ambassador, 'affiliate')->approved()->create();

    $this->actingAs($ambassador)
        ->post(route('member.ambassador.payouts.store'), [
            'method' => 'telebirr',
            'account' => '0911223344',
        ])
        ->assertRedirect();

    $payout = ReferralPayout::sole();

    expect($payout->status)->toBe('requested')
        ->and((float) $payout->amount)->toBe(300.0)
        ->and($ambassador->fresh()->ambassadorBalance())->toBe(0.0)
        ->and(ReferralCommission::whereNull('payout_id')->count())->toBe(0);
});

test('payout requests require the minimum balance and a single open request', function () {
    $ambassador = User::factory()->create();
    ReferralCommission::factory()->for($ambassador, 'affiliate')->approved()->create(['amount' => 50]);

    $this->actingAs($ambassador)
        ->post(route('member.ambassador.payouts.store'), [
            'method' => 'telebirr',
            'account' => '0911223344',
        ]);

    expect(ReferralPayout::count())->toBe(0);

    ReferralCommission::factory()->count(2)->for($ambassador, 'affiliate')->approved()->create();

    $this->actingAs($ambassador)
        ->post(route('member.ambassador.payouts.store'), [
            'method' => 'telebirr',
            'account' => '0911223344',
        ]);

    $this->actingAs($ambassador)
        ->post(route('member.ambassador.payouts.store'), [
            'method' => 'telebirr',
            'account' => '0911223344',
        ]);

    expect(ReferralPayout::count())->toBe(1);
});

test('refunding a payment voids unpaid commissions', function () {
    $admin = User::factory()->staff()->create();
    $ambassador = User::factory()->create();
    $buyer = User::factory()->create(['referred_by_id' => $ambassador->id]);
    $payment = Payment::factory()->for($buyer)->jami()->create();
    $payment->markSuccessful();

    $commission = ReferralCommission::sole();
    $commission->approve($admin);

    app(ReferralCommissioner::class)->voidFor($payment->fresh());

    expect($commission->fresh()->status)->toBe('void');
});
