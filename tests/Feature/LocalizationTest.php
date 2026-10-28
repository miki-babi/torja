<?php

use App\Models\Exercise;
use App\Models\Language;
use App\Models\LanguageLine;
use App\Models\User;
use App\Support\Translator;
use Inertia\Testing\AssertableInertia as Assert;

test('members can switch their locale and it is applied', function () {
    Language::factory()->create([
        'code' => 'am',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('locale.update'), ['locale' => 'am'])
        ->assertRedirect()
        ->assertCookie('locale', 'am');

    expect($user->refresh()->locale)->toBe('am');

    $this->actingAs($user)
        ->get(route('member.exercise'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'am'));
});

test('invalid locales are rejected', function () {
    Language::factory()->create(['code' => 'en', 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale');
});

test('guests locale is driven by the cookie', function () {
    Language::factory()->create([
        'code' => 'en',
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->withCookie('locale', 'en')
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->has('translations')
            ->has('languages'));
});

test('translator falls back to default then the key', function () {
    Language::factory()->create([
        'code' => 'en',
        'is_active' => true,
        'is_default' => true,
    ]);
    Language::factory()->create(['code' => 'am', 'is_active' => true]);
    LanguageLine::factory()->create([
        'group' => 'common',
        'key' => 'save',
        'text' => ['en' => 'Save'],
    ]);

    Translator::flush();

    expect(Translator::t('common', 'save', 'am'))->toBe('Save')
        ->and(Translator::t('common', 'missing', 'en'))->toBe('common.missing');
});

test('exercise names localize to amharic when available', function () {
    app()->setLocale('am');

    $exercise = Exercise::factory()->create([
        'name' => 'barbell bench press',
        'name_am' => 'ባርቤል ቤንች ፕሬስ',
    ]);

    expect($exercise->toSummary()['name'])->toBe('ባርቤል ቤንች ፕሬስ');

    app()->setLocale('en');
    expect($exercise->toSummary()['name'])->toBe('barbell bench press');
});

test('admins can manage languages and translations', function () {
    Language::factory()->create(['code' => 'en', 'is_default' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.languages.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.languages.store'), [
            'code' => 'ti',
            'name' => 'Tigrinya',
            'native_name' => 'ትግርኛ',
        ])
        ->assertRedirect();

    expect(Language::where('code', 'ti')->exists())->toBeTrue();

    $line = LanguageLine::factory()->create([
        'group' => 'common',
        'key' => 'save',
        'text' => ['en' => 'Save'],
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.translations.update', $line->id), [
            'text' => ['en' => 'Save', 'am' => 'አስቀምጥ'],
        ])
        ->assertRedirect();

    expect($line->refresh()->text['am'])->toBe('አስቀምጥ');
});

test('non-admins cannot access translation management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.languages.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.translations.index'))->assertForbidden();
});
