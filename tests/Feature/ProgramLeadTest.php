<?php

use App\Models\ExerciseProgram;
use App\Models\ProgramLead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function freeSharedProgram(): ExerciseProgram
{
    $trainerUser = User::factory()->trainer()->create();
    $trainerUser->ensureTrainer();

    return ExerciseProgram::factory()
        ->for($trainerUser)
        ->create(['price_etb' => 0, 'share_token' => 'tok_'.fake()->unique()->lexify('??????')]);
}

test('a guest answers the questionnaire and becomes a lead', function () {
    $program = freeSharedProgram();

    $this->get(route('programs.shared.lead', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/program-lead')
            ->has('questions'));

    $this->post(route('programs.shared.lead.store', $program->share_token), [
        'name' => 'Abebe Bikila',
        'phone' => '0911223344',
        'answers' => [
            'goal' => 'lose_fat',
            'experience' => 'beginner',
            'days_per_week' => '3-4',
            'health' => 'none',
        ],
    ])->assertRedirect(route('programs.shared.lead.thanks', $program->share_token));

    $lead = ProgramLead::sole();
    expect($lead->name)->toBe('Abebe Bikila')
        ->and($lead->trainer_id)->toBe($program->user->trainer->id)
        ->and($lead->user_id)->toBeNull()
        ->and($lead->answers['goal'])->toBe('lose_fat');

    // The session carries both flags for the post-auth claim.
    $this->get(route('programs.shared.lead.thanks', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/program-lead-thanks'));
});

test('registering after the questionnaire links the member and stamps the lead', function () {
    $program = freeSharedProgram();
    $trainer = $program->user->trainer;

    $this->post(route('programs.shared.lead.store', $program->share_token), [
        'name' => 'Tirunesh Dibaba',
        'answers' => [
            'goal' => 'sport',
            'experience' => 'advanced',
            'days_per_week' => '5+',
            'health' => 'none',
        ],
    ]);

    $this->post(route('register'), [
        'name' => 'Tirunesh Dibaba',
        'email' => 'tirunesh@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'tirunesh@example.com')->sole();

    expect($user->isClientOf($trainer->user))->toBeTrue()
        ->and(ProgramLead::sole()->user_id)->toBe($user->id);
});

test('the lead funnel only runs for free trainer programs', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainerUser->ensureTrainer();
    $paid = ExerciseProgram::factory()->for($trainerUser)->create([
        'price_etb' => 500,
        'share_token' => 'tok_paid01',
    ]);

    $this->get(route('programs.shared.lead', $paid->share_token))
        ->assertRedirect(route('programs.shared.show', $paid->share_token));

    $this->post(route('programs.shared.lead.store', $paid->share_token), [
        'name' => 'X',
        'answers' => ['goal' => 'lose_fat', 'experience' => 'beginner', 'days_per_week' => '1-2', 'health' => 'none'],
    ])->assertNotFound();
});

test('the shared page exposes the lead url to guests and the offer to everyone', function () {
    $program = freeSharedProgram();
    $program->user->trainer->update(['offer_text' => 'Train 1:1 with me']);

    $this->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leadUrl', route('programs.shared.lead', $program->share_token))
            ->where('offerText', 'Train 1:1 with me')
            ->where('trainerUrl', $program->user->trainer->miniAppUrl()));

    // Members get no lead url — they claim directly.
    $member = User::factory()->create();
    $this->actingAs($member)
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leadUrl', null));
});

test('the trainer leads screen shows stats and conversion', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();

    ProgramLead::factory()->for($trainer)->count(3)->create();
    ProgramLead::factory()->for($trainer)->create([
        'user_id' => User::factory(),
        'created_at' => now()->subWeeks(2),
    ]);

    $this->actingAs($trainerUser)
        ->get(route('trainer.leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 4)
            ->where('stats.converted', 1)
            ->where('stats.new_this_week', 3)
            ->has('leads', 4));
});
