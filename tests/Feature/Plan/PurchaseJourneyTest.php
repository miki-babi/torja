<?php

use App\Enums\MealType;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\MealPlanMeal;
use App\Models\NutritionProfile;
use App\Models\Payment;
use App\Models\Trainer;
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

    $user = User::sole();

    // Members reach the app through a coach — member routes and checkout
    // are gated on a trainer link.
    Trainer::factory()->create()->clients()->attach($user->id);

    // Email verification is currently not gated — new accounts reach the
    // preview straight away.
    $this->get(route('plan.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unlocked', false)
            ->has('sampleDay.meals'));

    // The guest profile was claimed on the first authed request, even though
    // registration regenerated the session id.
    expect($user->nutritionProfile)->not->toBeNull()
        ->and($user->nutritionProfile->isCompleted())->toBeTrue();

    // Pay via Jami.
    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'sessionId' => 'cs_journey',
            'mode' => 'redirect',
            'checkoutUrl' => 'https://jami.bio/jamidev/pay/cs_journey',
        ]),
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => 'cs_journey',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    // Per-request header: withHeader() would persist X-Inertia into later
    // requests and trip Inertia's asset-version conflict (409).
    $this->post(route('plan.checkout'), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://jami.bio/jamidev/pay/cs_journey');

    $payment = Payment::sole();
    expect($payment->purpose)->toBe('pro');

    $this->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($user->fresh()->isPro())->toBeTrue();

    // A member-owned plan spanning the week — the meal-program viewer only
    // serves plans the user built or claimed, never the global PDF plans.
    $plan = MealPlan::factory()->for($user)->create();

    foreach ([1, 7] as $day) {
        $meal = MealPlanMeal::factory()->for($plan, 'mealPlan')->create([
            'day' => $day,
            'meal_type' => MealType::Lunch,
        ]);
        MealPlanItem::factory()->for($meal, 'meal')->create(['food_id' => null]);
    }

    // The full week is now unlocked on the member meal-program screen.
    $this->get(route('member.meal-programs.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('days.1.meals')
            ->has('days.7.meals'));
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
