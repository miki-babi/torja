<?php

use App\Models\NutritionProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validBodyPayload(): array
{
    return [
        'age' => 30,
        'height_cm' => 175,
        'weight_kg' => 80,
        'sex' => 'male',
        'weight_trend' => 'stable',
        'medical_flags' => ['none'],
    ];
}

test('guests can view the wizard and start at step one', function () {
    $this->get(route('plan.wizard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan/wizard')
            ->where('currentStep', 1)
            ->where('status', 'in_progress'));

    $profile = NutritionProfile::sole();
    expect($profile->user_id)->toBeNull()
        ->and($profile->session_id)->not->toBeNull();
});

test('guests can save a section and answers persist', function () {
    $this->withCookie(config('session.cookie'), 'body1sessionidxxxxxxxxxxxxxxxxxxxxxxxxxx');

    $this->patch(route('plan.wizard.update', 'body'), validBodyPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = NutritionProfile::sole();
    expect($profile->body['age'])->toBe(30)
        ->and($profile->current_step)->toBe(4)
        ->and($profile->status)->toBe('in_progress');

    $this->get(route('plan.wizard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStep', 4)
            ->where('answers.body.age', 30)
            ->where('answers.body.sex', 'male'));
});

test('guests resume the wizard at their saved step', function () {
    $this->withCookie(config('session.cookie'), 'resume1sessionidxxxxxxxxxxxxxxxxxxxxxxxx');

    $this->patch(route('plan.wizard.update', 'goal'), [
        'primary_goal' => 'lose_fat',
        'motivations' => ['health'],
        'priorities' => ['high_protein'],
    ])->assertSessionHasNoErrors();

    $this->get(route('plan.wizard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStep', 3)
            ->where('answers.goal.primary_goal', 'lose_fat'));
});

test('a different session does not see another guest answers', function () {
    $this->withCookie(config('session.cookie'), 'a1sessionidxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
    $this->patch(route('plan.wizard.update', 'body'), validBodyPayload());

    $this->withCookie(config('session.cookie'), 'b1sessionidxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
    $this->get(route('plan.wizard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentStep', 1));
});

test('invalid payloads redirect back with errors', function () {
    $this->withCookie(config('session.cookie'), 'invalid1sessionidxxxxxxxxxxxxxxxxxxxxxxx');

    $this->patch(route('plan.wizard.update', 'body'), array_merge(validBodyPayload(), [
        'age' => 0,
    ]))->assertSessionHasErrors('age');

    // The form request rejects the payload before the resolver runs.
    expect(NutritionProfile::count())->toBe(0);
});

test('unknown sections are rejected', function () {
    $this->patch('/plan/wizard/not-a-section', [])->assertNotFound();
});

test('conditional fields are validated server side', function () {
    // motivation_other required when "other" is selected
    $this->patch(route('plan.wizard.update', 'goal'), [
        'primary_goal' => 'lose_fat',
        'motivations' => ['other'],
        'priorities' => [],
    ])->assertSessionHasErrors('motivation_other');

    // fasting_note required when fasting is "custom"
    $this->patch(route('plan.wizard.update', 'diet'), [
        'diet_type' => 'omnivore',
        'fasting' => 'custom',
        'adapt_to_fasting' => true,
    ])->assertSessionHasErrors('fasting_note');

    // fasting_note prohibited when fasting is "none"
    $this->patch(route('plan.wizard.update', 'diet'), [
        'diet_type' => 'omnivore',
        'fasting' => 'none',
        'fasting_note' => 'should not be here',
        'adapt_to_fasting' => false,
    ])->assertSessionHasErrors('fasting_note');

    // cook_time required unless cook is "prepared"
    $this->patch(route('plan.wizard.update', 'cooking'), [
        'cook' => 'self',
        'kitchen' => ['stove'],
        'repetition' => 'fine',
    ])->assertSessionHasErrors('cook_time');

    // cook_time and kitchen are prohibited when cook is "prepared"
    $this->patch(route('plan.wizard.update', 'cooking'), [
        'cook' => 'prepared',
        'cook_time' => 'lt15',
        'kitchen' => ['stove'],
        'repetition' => 'fine',
    ])->assertSessionHasErrors(['cook_time', 'kitchen']);

    // cook is "prepared" without cook_time/kitchen is valid
    $this->patch(route('plan.wizard.update', 'cooking'), [
        'cook' => 'prepared',
        'repetition' => 'fine',
    ])->assertSessionHasNoErrors();
});

test('authenticated users claim their session profile', function () {
    $this->withCookie(config('session.cookie'), 'claim1sessionidxxxxxxxxxxxxxxxxxxxxxxxxx');
    $this->patch(route('plan.wizard.update', 'body'), validBodyPayload());

    $profile = NutritionProfile::sole();
    expect($profile->user_id)->toBeNull();

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('plan.wizard'));

    $profile->refresh();
    expect($profile->user_id)->toBe($user->id)
        ->and(NutritionProfile::count())->toBe(1);
});

test('an existing user profile wins over the session profile', function () {
    $user = User::factory()->create();
    $owned = NutritionProfile::factory()->forUser($user)->create([
        'current_step' => 5,
    ]);

    $this->withCookie(config('session.cookie'), 'wins1sessionidxxxxxxxxxxxxxxxxxxxxxxxxxx');
    $this->patch(route('plan.wizard.update', 'body'), validBodyPayload());

    $this->actingAs($user)->get(route('plan.wizard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentStep', 5));

    // The session profile is left untouched, and no new row is created.
    expect(NutritionProfile::count())->toBe(2)
        ->and(NutritionProfile::whereNull('user_id')->count())->toBe(1);
});

test('submitting the final section completes the profile', function () {
    $this->withCookie(config('session.cookie'), 'final1sessionidxxxxxxxxxxxxxxxxxxxxxxxxx');

    $this->patch(route('plan.wizard.update', 'obstacles'), [
        'obstacles' => ['no_time'],
        'support' => ['meal_reminders'],
    ])->assertSessionHasNoErrors();

    $profile = NutritionProfile::sole();
    expect($profile->status)->toBe('completed')
        ->and($profile->completed_at)->not->toBeNull();
});

test('review redirects to the wizard when nothing is answered', function () {
    $this->withCookie(config('session.cookie'), 'review1sessionidxxxxxxxxxxxxxxxxxxxxxxxx');

    $this->get(route('plan.review'))->assertRedirect(route('plan.wizard'));
});

test('review renders the saved answers', function () {
    $this->withCookie(config('session.cookie'), 'review2sessionidxxxxxxxxxxxxxxxxxxxxxxxx');
    $this->patch(route('plan.wizard.update', 'body'), validBodyPayload());

    $this->get(route('plan.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan/review')
            ->where('answers.body.age', 30));
});
