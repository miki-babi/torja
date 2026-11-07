<?php

use App\Models\Trainer;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('admins see the trainers list with stats', function () {
    $admin = User::factory()->admin()->create();
    $trainer = Trainer::factory()->withBot()->create();

    $this->actingAs($admin)
        ->get(route('admin.trainers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/trainers/index')
            ->where('stats.trainers', 1)
            ->has('trainers.data', 1)
            ->where('trainers.data.0.bot_connected', true));
});

test('admins see a trainer detail page', function () {
    $admin = User::factory()->admin()->create();
    $trainer = Trainer::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.trainers.show', $trainer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/trainers/show')
            ->where('trainer.slug', $trainer->slug));
});

test('admins can promote a member by email', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['email' => 'newbie@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.trainers.promote'), ['email' => 'newbie@example.com'])
        ->assertRedirect();

    expect($member->fresh()->isTrainer())->toBeTrue();
    expect($member->fresh()->trainer)->not->toBeNull();
});

test('promoting an unknown email does not create anything', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.trainers.promote'), ['email' => 'nobody@example.com'])
        ->assertRedirect();

    expect(Trainer::count())->toBe(0);
});

test('admins can demote a trainer back to member', function () {
    $admin = User::factory()->admin()->create();
    $trainer = Trainer::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.trainers.demote', $trainer))
        ->assertRedirect();

    expect($trainer->user->fresh()->role)->toBe('member');
    expect(Trainer::find($trainer->id))->not->toBeNull();
});

test('admins can suspend a trainer bot', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $admin = User::factory()->admin()->create();
    $trainer = Trainer::factory()->withBot()->create();

    $this->actingAs($admin)
        ->post(route('admin.trainers.suspend-bot', $trainer))
        ->assertRedirect();

    expect($trainer->fresh()->bot_token)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook'));
});

test('deleting a trainer removes the tenant but keeps the user', function () {
    $admin = User::factory()->admin()->create();
    $trainer = Trainer::factory()->create();
    $client = User::factory()->create();
    $trainer->clients()->attach($client->id);
    $owner = $trainer->user;

    $this->actingAs($admin)
        ->delete(route('admin.trainers.destroy', $trainer))
        ->assertRedirect(route('admin.trainers.index'));

    expect(Trainer::find($trainer->id))->toBeNull();
    expect(TrainerClient::count())->toBe(0);
    expect($owner->fresh())->not->toBeNull();
});

test('non-admins cannot manage trainers', function () {
    $user = User::factory()->create();
    $trainer = Trainer::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.trainers.demote', $trainer))
        ->assertForbidden();
});
