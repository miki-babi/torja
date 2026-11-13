<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the claim-form builder shows the mode and default-questionnaire flag', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainerUser->ensureTrainer();

    $this->actingAs($trainerUser)
        ->get(route('trainer.lead-form.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('trainer/lead-form')
            ->where('mode', 'required')
            ->where('usingDefaults', true)
            ->has('defaultFields', 4)
            ->has('fields', 0));
});

test('a trainer saves a custom claim form', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();

    $this->actingAs($trainerUser)
        ->patch(route('trainer.lead-form.update'), [
            'mode' => 'optional',
            'fields' => [
                ['label' => 'Your goal?', 'type' => 'text', 'required' => true],
                ['label' => 'Experience?', 'type' => 'select', 'options' => ['New', 'Some'], 'required' => false],
            ],
        ])
        ->assertRedirect();

    expect($trainer->fresh()->lead_form_mode)->toBe('optional');

    $fields = $trainer->leadFields()->get();
    expect($fields)->toHaveCount(2)
        ->and($fields[0]->label)->toBe('Your goal?')
        ->and($fields[0]->position)->toBe(0)
        ->and($fields[1]->type)->toBe('select')
        ->and($fields[1]->options)->toBe(['New', 'Some'])
        ->and($fields[1]->required)->toBeFalse();
});

test('saving no fields reverts to the preset questionnaire', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();
    $trainer->leadFields()->create([
        'label' => 'Old question',
        'type' => 'text',
        'required' => true,
        'position' => 0,
    ]);

    $this->actingAs($trainerUser)
        ->patch(route('trainer.lead-form.update'), ['mode' => 'required', 'fields' => []])
        ->assertRedirect();

    expect($trainer->leadFields()->count())->toBe(0)
        ->and($trainer->leadFormFields())->toHaveCount(4);
});

test('a member cannot reach the claim-form builder', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('trainer.lead-form.edit'))
        ->assertForbidden();
});
