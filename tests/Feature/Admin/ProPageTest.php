<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\ProPage;
use Inertia\Testing\AssertableInertia as Assert;

test('non-admins cannot manage the pro page settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.pro-page.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.pro-page.update'), [])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.pro-page.reset'))->assertForbidden();
});

test('admins see the defaults when nothing was saved', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pro-page.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pro-page/edit')
            ->where('settings.headline.en', 'Go Pro.')
            ->where('defaults.headline.en', 'Go Pro.')
            ->where('envPrice', (int) config('plans.pro_price_etb'))
            ->has('icons'));
});

test('admins can save pro page settings', function () {
    $payload = ProPage::defaults();
    $payload['headline']['en'] = 'Join Pro.';
    $payload['headline']['am'] = 'ፕሮ ተቀላቀል።';
    $payload['price_etb'] = 750;
    $payload['features'][0]['starter'] = true;

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pro-page.update'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $stored = Setting::get(ProPage::SETTING_KEY);
    expect($stored['headline']['en'])->toBe('Join Pro.')
        ->and($stored['price_etb'])->toBe(750)
        ->and($stored['features'][0]['starter'])->toBeTrue();
});

test('saved settings show up on the pro page and checkout', function () {
    Setting::set(ProPage::SETTING_KEY, [
        'headline' => ['en' => 'Custom headline', 'am' => ''],
        'price_etb' => 750,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pro.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.headline', 'Custom headline')
            ->where('page.subheadline', 'Everything you need to build your strongest self.')
            ->where('price', 750));
});

test('invalid settings are rejected', function () {
    $admin = User::factory()->admin()->create();

    $payload = ProPage::defaults();
    $payload['price_etb'] = 'abc';
    $this->actingAs($admin)
        ->put(route('admin.pro-page.update'), $payload)
        ->assertSessionHasErrors('price_etb');

    $payload = ProPage::defaults();
    $payload['features'][0]['icon'] = 'not-an-icon';
    $this->actingAs($admin)
        ->put(route('admin.pro-page.update'), $payload)
        ->assertSessionHasErrors('features.0.icon');

    $payload = ProPage::defaults();
    $payload['features'][0]['title']['en'] = '';
    $this->actingAs($admin)
        ->put(route('admin.pro-page.update'), $payload)
        ->assertSessionHasErrors('features.0.title.en');
});

test('admins can reset the pro page to defaults', function () {
    Setting::set(ProPage::SETTING_KEY, ['headline' => ['en' => 'Custom', 'am' => '']]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.pro-page.reset'))
        ->assertRedirect();

    expect(Setting::get(ProPage::SETTING_KEY))->toBeNull();

    $this->actingAs(User::factory()->create())
        ->get(route('pro.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.headline', 'Go Pro.'));
});
