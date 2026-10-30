<?php

use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\ProPage;
use Inertia\Testing\AssertableInertia as Assert;

test('the pro page renders the default copy', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pro.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pro')
            ->where('isPro', false)
            ->where('page.headline', 'Go Pro.')
            ->where('page.headline_accent', 'Train Better.')
            ->where('page.features.0.title', 'Workout programs')
            ->where('page.features.0.starter', false)
            ->where('page.features.0.pro', true)
            ->where('page.free_button', 'Continue with free')
            ->where('page.free_notice', 'Keep using Toraj for free — you can unlock Pro anytime from your profile.')
            ->where('page.card_title', 'Toraj Pro')
            ->where('homeUrl', route('member.home'))
            ->where('checkoutUrl', route('plan.checkout')));
});

test('pro members see the pro member state copy', function () {
    $this->actingAs(proUser())
        ->get(route('pro.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isPro', true)
            ->where('page.pro_title', "You're Pro"));
});

test('an admin-set price is charged at checkout', function () {
    Setting::set(ProPage::SETTING_KEY, ['price_etb' => 750]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plan.checkout'), ['provider' => 'chapa']);

    expect((float) Payment::where('user_id', $user->id)->latest('id')->first()?->amount)
        ->toBe(750.0);
});

test('the env price is used when no override exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plan.checkout'), ['provider' => 'chapa']);

    expect((float) Payment::where('user_id', $user->id)->latest('id')->first()?->amount)
        ->toBe((float) config('plans.pro_price_etb'));
});

test('copy localizes to amharic with an english fallback', function () {
    Setting::set(ProPage::SETTING_KEY, [
        'headline' => ['en' => 'Go Pro.', 'am' => 'ፕሮ ሂድ'],
        'subheadline' => ['en' => 'English only', 'am' => ''],
    ]);

    $am = ProPage::forMember('am');
    expect($am['headline'])->toBe('ፕሮ ሂድ')
        ->and($am['subheadline'])->toBe('English only');

    $en = ProPage::forMember('en');
    expect($en['headline'])->toBe('Go Pro.');
});

test('the price placeholder is substituted with the resolved price', function () {
    Setting::set(ProPage::SETTING_KEY, [
        'price_etb' => 1250,
        'sheet_subtitle' => ['en' => '{price} ETB, yours forever.', 'am' => ''],
    ]);

    $copy = ProPage::forMember('en');
    expect($copy['sheet_subtitle'])->toBe('1,250 ETB, yours forever.');
});
