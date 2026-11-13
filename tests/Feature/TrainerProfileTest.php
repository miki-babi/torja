<?php

use App\Models\ExerciseProgram;
use App\Models\Trainer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see the public trainer page', function () {
    $trainer = Trainer::factory()->create([
        'bio' => 'Strength coach from Addis.',
        'specialty' => 'Powerlifting',
    ]);

    ExerciseProgram::factory()->for($trainer->user)->create([
        'name' => '7-Day Starter',
        'price_etb' => 0,
        'share_token' => 'tok-123',
    ]);

    $this->get("/t/{$trainer->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/trainer-page')
            ->where('trainer.name', $trainer->display_name)
            ->where('trainer.bio', 'Strength coach from Addis.')
            ->where('trainer.specialty', 'Powerlifting')
            ->has('programs', 1)
            ->where('programs.0.name', '7-Day Starter')
            ->where('programs.0.is_free', true));
});

test('members hitting the trainer link get linked and land in the app', function () {
    $trainer = Trainer::factory()->create();
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get("/t/{$trainer->slug}")
        ->assertRedirect(route('member.home'));

    expect($client->fresh()->isClientOf($trainer->user))->toBeTrue();
});

test('a guest who registers after viewing the page joins that trainer', function () {
    $trainer = Trainer::factory()->create();

    $this->get("/t/{$trainer->slug}")->assertOk();
    $this->post('/register', [
        'name' => 'New Client',
        'email' => 'newclient@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::where('email', 'newclient@example.com')->firstOrFail();

    expect($trainer->fresh()->clients()->whereKey($user->id)->exists())->toBeTrue();
});

test('trainers can update their profile and payout details', function () {
    $user = User::factory()->trainer()->create();

    $this->actingAs($user)->patch('/trainer/profile', [
        'bio' => 'I coach busy professionals.',
        'specialty' => 'Fat loss',
        'languages' => ['Amharic', 'English'],
        'years_experience' => 6,
        'payout_method' => 'telebirr',
        'payout_account' => '0911223344',
        'offer_text' => 'Train with me 1:1.',
    ])->assertRedirect();

    $trainer = $user->trainer->fresh();

    expect($trainer->bio)->toBe('I coach busy professionals.')
        ->and($trainer->specialty)->toBe('Fat loss')
        ->and($trainer->languages)->toBe(['Amharic', 'English'])
        ->and($trainer->years_experience)->toBe(6)
        ->and($trainer->payout_method)->toBe('telebirr')
        ->and($trainer->payout_account)->toBe('0911223344')
        ->and($trainer->offer_text)->toBe('Train with me 1:1.');
});

test('the trainer profile form renders', function () {
    $user = User::factory()->trainer()->create();

    $this->actingAs($user)
        ->get('/trainer/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('trainer/profile'));
});
