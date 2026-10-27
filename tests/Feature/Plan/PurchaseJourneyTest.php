<?php

use App\Models\NutritionProfile;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FoodDatabaseSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('a guest completes the wizard, registers, pays and unlocks the full plan', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $this->withCookie(config('session.cookie'), 'journey'.str_repeat('x', 33));

    // Guest answers the wizard sections needed for a complete profile.
    $this->patch(route('plan.wizard.update', 'goal'), [
        'primary_goal' => 'lose_fat',
        'motivations' => ['health'],
        'priorities' => ['high_protein'],
    ])->assertSessionHasNoErrors();

    $this->patch(route('plan.wizard.update', 'body'), [
        'age' => 30,
        'height_cm' => 175,
        'weight_kg' => 80,
        'sex' => 'male',
        'weight_trend' => 'stable',
        'medical_flags' => ['none'],
    ])->assertSessionHasNoErrors();

    $this->patch(route('plan.wizard.update', 'obstacles'), [
        'obstacles' => ['no_time'],
        'support' => [],
    ])->assertSessionHasNoErrors();

    $this->get(route('plan.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('status', 'completed'));

    // The preview requires auth — visiting it sets the intended URL the
    // register response honours.
    $this->get(route('plan.preview'))->assertRedirect(route('login'));

    $this->post(route('register'), [
        'name' => 'Journey User',
        'email' => 'journey@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('plan.preview'));

    $this->assertAuthenticated();

    // New accounts land on the verification notice; verifying continues the
    // funnel back to the preview (covered by Fortify's own tests).
    $this->get(route('plan.preview'))
        ->assertRedirect(route('verification.notice'));

    $user = User::sole();
    $user->forceFill(['email_verified_at' => now()])->save();
    // The session guard memoizes the resolved user between requests — re-auth
    // with the fresh instance so the verified flag is seen.
    $this->actingAs($user->fresh());

    $this->get(route('plan.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unlocked', false)
            ->has('sampleDay.meals'));

    // The guest profile was claimed on the first authed request, even though
    // registration regenerated the session id.
    expect($user->nutritionProfile)->not->toBeNull()
        ->and($user->nutritionProfile->isCompleted())->toBeTrue();

    // Pay via Chapa.
    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/journey'],
        ]),
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    // Per-request header: withHeader() would persist X-Inertia into later
    // requests and trip Inertia's asset-version conflict (409).
    $this->post(route('plan.checkout'), ['provider' => 'chapa'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.chapa.co/journey');

    $payment = Payment::sole();
    expect($payment->purpose)->toBe('pro');

    $this->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($user->fresh()->isPro())->toBeTrue();

    // The full week is now unlocked on the meal screen.
    $this->get(route('member.meal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unlocked', true)
            ->has('planDays.1.meals')
            ->has('planDays.7.meals'));
});

test('guests who already paid land on an unlocked preview', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    NutritionProfile::factory()->forUser($user)->completed()->create();
    Payment::factory()->for($user)->successful()->create();

    $this->actingAs($user)
        ->get(route('plan.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('unlocked', true));
});
