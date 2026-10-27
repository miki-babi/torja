<?php

use App\Models\Food;
use App\Models\MealPlan;
use App\Models\MealProgram;
use App\Models\MealProgramAccess;
use App\Models\Payment;
use App\Models\User;
use App\Services\Programs\MealProgramAccessGranter;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function sharedMealProgram(array $attributes = []): MealProgram
{
    $injera = Food::factory()->create(['name_en' => 'Injera']);
    $shiro = Food::factory()->create(['name_en' => 'Shiro']);
    $bread = Food::factory()->create(['name_en' => 'Bread']);

    $program = MealProgram::factory()->create($attributes);

    $program->meals()->create(['day' => 1, 'meal_type' => 'breakfast', 'position' => 0])
        ->items()->createMany([
            ['food_id' => $bread->id, 'portion_note' => '2 slices', 'position' => 0],
        ]);

    $program->meals()->create(['day' => 1, 'meal_type' => 'lunch', 'position' => 1])
        ->items()->createMany([
            ['food_id' => $injera->id, 'portion_note' => '1 roll', 'position' => 0],
            ['food_id' => $shiro->id, 'portion_note' => '1 cup', 'position' => 1],
        ]);

    $program->meals()->create(['day' => 2, 'meal_type' => 'dinner', 'position' => 0])
        ->items()->createMany([
            ['food_id' => $injera->id, 'portion_note' => null, 'position' => 0],
        ]);

    return $program;
}

test('guests can view a shared meal program with its days and items', function () {
    $program = sharedMealProgram(['name' => 'Ethiopian Week']);

    $this->get(route('meal-programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/shared-meal-program')
            ->where('program.name', 'Ethiopian Week')
            ->where('program.image_url', null)
            ->where('program.highlights', [])
            ->has('program.days', 2)
            ->has('program.days.0.meals', 2)
            ->where('program.days.0.meals.0.type', 'breakfast')
            ->where('program.days.0.meals.1.items.0.name', 'Injera')
            ->where('alreadyClaimed', false));
});

test('the shared page exposes the cover image and highlights', function () {
    $program = sharedMealProgram([
        'image_path' => 'meal-programs/hero.jpg',
        'highlights' => ['Lose weight', 'Seven guided days'],
    ]);

    $this->get(route('meal-programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/shared-meal-program')
            ->where('program.image_url', fn (string $url): bool => str_contains($url, 'meal-programs/hero.jpg'))
            ->where('program.highlights', ['Lose weight', 'Seven guided days']));
});

test('unshared meal programs return 404', function () {
    $program = MealProgram::factory()->unshared()->create();

    $this->get('/shared/meal-programs/not-a-token')->assertNotFound();

    expect($program->shareUrl())->toBeNull();
});

test('guests are sent to login when they try to save', function () {
    $program = sharedMealProgram();

    $this->post(route('meal-programs.shared.save', $program->share_token))
        ->assertRedirect(route('login'));
});

test('pro members can claim a meal program which copies its days into a meal plan', function () {
    $program = sharedMealProgram(['name' => 'Starter Plan']);
    $user = proUser();

    $this->actingAs($user)
        ->postJson(route('meal-programs.shared.save', $program->share_token))
        ->assertCreated()
        ->assertJsonPath('claimed', true)
        ->assertJsonPath('plan_url', fn (string $url): bool => str_contains($url, 'member/meal-programs/'));

    expect(MealProgramAccess::sole()->meal_program_id)->toBe($program->id);

    $plan = MealPlan::sole();
    expect($plan->user_id)->toBe($user->id)
        ->and($plan->meal_program_id)->toBe($program->id)
        ->and($plan->name)->toBe('Starter Plan')
        ->and($plan->meals()->count())->toBe(3)
        ->and($plan->meals()->where('day', 1)->where('meal_type', 'lunch')->sole()
            ->items()->pluck('portion_note')->all())->toBe(['1 roll', '1 cup'])
        ->and($plan->meals()->where('day', 2)->sole()->day)->toBe(2);
});

test('claiming a meal program twice is idempotent', function () {
    $program = sharedMealProgram();
    $user = proUser();

    $this->actingAs($user)
        ->postJson(route('meal-programs.shared.save', $program->share_token))
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('meal-programs.shared.save', $program->share_token))
        ->assertOk();

    expect(MealPlan::count())->toBe(1)
        ->and(MealProgramAccess::count())->toBe(1);
});

test('a claimed meal program shows as claimed on the shared page', function () {
    $program = sharedMealProgram();
    $user = User::factory()->create();
    app(MealProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->get(route('meal-programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alreadyClaimed', true)
            ->where('claimedPlanUrl', fn (string $url): bool => str_contains($url, 'member/meal-programs/')));
});

test('non-pro members cannot claim a meal program', function () {
    $program = sharedMealProgram();

    $this->actingAs(User::factory()->create())
        ->postJson(route('meal-programs.shared.save', $program->share_token))
        ->assertForbidden();
});

test('a successful legacy meal program payment still grants access and does not grant pro', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $program = sharedMealProgram(['price_etb' => 300]);
    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create([
        'status' => 'pending',
        'purpose' => 'meal_program_purchase',
        'payable_type' => $program->getMorphClass(),
        'payable_id' => $program->id,
    ]);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('meal-programs.shared.show', $program->share_token));

    expect($payment->fresh()->isSuccessful())->toBeTrue()
        ->and(MealProgramAccess::sole()->payment_id)->toBe($payment->id)
        ->and(MealPlan::where('user_id', $user->id)->count())->toBe(1)
        // A legacy meal program purchase does not grant Pro.
        ->and($user->fresh()->isPro())->toBeFalse();
});

test('the webhook also fulfills a legacy meal program purchase', function () {
    config(['services.chapa.webhook_secret' => 'wh-secret']);

    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $program = sharedMealProgram(['price_etb' => 300]);
    $payment = Payment::factory()->create([
        'purpose' => 'meal_program_purchase',
        'payable_type' => $program->getMorphClass(),
        'payable_id' => $program->id,
    ]);

    $body = json_encode(['tx_ref' => $payment->tx_ref]);
    $signature = hash_hmac('sha256', $body, 'wh-secret');

    $this->call('POST', route('payment.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CHAPA_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    expect($payment->fresh()->isSuccessful())->toBeTrue()
        ->and(MealProgramAccess::count())->toBe(1)
        ->and(MealPlan::where('user_id', $payment->user_id)->count())->toBe(1);
});

test('a second claim on an already-owned meal program copies nothing again', function () {
    $program = sharedMealProgram();
    $user = proUser();
    app(MealProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->postJson(route('meal-programs.shared.save', $program->share_token))
        ->assertOk();

    expect(MealPlan::count())->toBe(1)
        ->and(MealProgramAccess::count())->toBe(1);
});
