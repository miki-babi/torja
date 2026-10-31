<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\HomeBanners;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('non-admins cannot manage home banners', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.home-banners.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.home-banners.update'), [])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.home-banners.reset'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.home-banners.upload-image'), [])->assertForbidden();
});

test('admins see the saved banners on the editor', function () {
    Setting::set(HomeBanners::SETTING_KEY, [
        'banners' => [['image' => 'home-banners/a.jpg', 'url' => '/pro', 'cta' => 'Go Pro']],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.home-banners.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/home-banners/edit')
            ->where('banners.0.image', 'home-banners/a.jpg')
            ->where('banners.0.url', '/pro')
            ->where('banners.0.cta', 'Go Pro')
            ->where('resolved.0.cta', 'Go Pro')
            ->where('resolved.0.external', false)
            ->has('resolved.0.image'));
});

test('admins can save home banners', function () {
    $payload = [
        'banners' => [
            ['image' => 'https://cdn.example.com/a.jpg', 'url' => 'https://example.com/offer', 'cta' => 'Shop now'],
            ['image' => 'home-banners/b.jpg', 'url' => '/pro', 'cta' => 'Go Pro'],
        ],
    ];

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.home-banners.update'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $stored = Setting::get(HomeBanners::SETTING_KEY);
    expect($stored['banners'])->toHaveCount(2)
        ->and($stored['banners'][1]['url'])->toBe('/pro');
});

test('invalid banners are rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.home-banners.update'), [
            'banners' => [['image' => '', 'url' => '/pro', 'cta' => 'Go']],
        ])
        ->assertSessionHasErrors('banners.0.image');

    $this->actingAs($admin)
        ->put(route('admin.home-banners.update'), [
            'banners' => [['image' => 'a.jpg', 'url' => 'example.com/no-scheme', 'cta' => 'Go']],
        ])
        ->assertSessionHasErrors('banners.0.url');

    $this->actingAs($admin)
        ->put(route('admin.home-banners.update'), [
            'banners' => [['image' => 'a.jpg', 'url' => 'javascript:alert(1)', 'cta' => 'Go']],
        ])
        ->assertSessionHasErrors('banners.0.url');

    $this->actingAs($admin)
        ->put(route('admin.home-banners.update'), [
            'banners' => [['image' => 'a.jpg', 'url' => '//protocol-relative.example.com', 'cta' => 'Go']],
        ])
        ->assertSessionHasErrors('banners.0.url');

    $this->actingAs($admin)
        ->put(route('admin.home-banners.update'), [
            'banners' => [['image' => 'a.jpg', 'url' => '/pro']],
        ])
        ->assertSessionHasErrors('banners.0.cta');
});

test('admins can clear the home banners', function () {
    Setting::set(HomeBanners::SETTING_KEY, [
        'banners' => [['image' => 'a.jpg', 'url' => '/pro']],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.home-banners.reset'))
        ->assertRedirect();

    expect(Setting::get(HomeBanners::SETTING_KEY))->toBeNull();
});

test('admins can upload banner images', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.home-banners.upload-image'), [
            'image' => UploadedFile::fake()->image('banner.jpg'),
        ])
        ->assertOk()
        ->assertJsonStructure(['path', 'url']);

    Storage::disk('public')->assertExists(
        collect(Storage::disk('public')->files('home-banners'))->first(),
    );

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.home-banners.upload-image'), [
            'image' => 'not-a-file',
        ])
        ->assertSessionHasErrors('image');
});
