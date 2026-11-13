<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use App\Models\TrainerPayout;
use App\Models\User;

function staffUser(): User
{
    return User::factory()->staff()->create();
}

test('staff can suspend a trainer — trainer area and public page close', function () {
    $trainer = Trainer::factory()->create();
    $admin = staffUser();

    $this->actingAs($admin)
        ->post(route('admin.trainers.suspend', $trainer))
        ->assertRedirect();

    expect($trainer->fresh()->isSuspended())->toBeTrue();

    $this->actingAs($trainer->user)
        ->get(route('trainer.dashboard'))
        ->assertForbidden();

    $this->get(route('tenant.entry', $trainer->slug))
        ->assertNotFound();
});

test('staff can reinstate a suspended trainer', function () {
    $trainer = Trainer::factory()->create(['suspended_at' => now()]);

    $this->actingAs(staffUser())
        ->post(route('admin.trainers.unsuspend', $trainer))
        ->assertRedirect();

    expect($trainer->fresh()->isSuspended())->toBeFalse();

    $this->actingAs($trainer->user)
        ->get(route('trainer.dashboard'))
        ->assertOk();
});

test('a payout batch covers all unpaid earnings and can be marked sent', function () {
    $trainer = Trainer::factory()->create();
    $earnings = TrainerEarning::factory()->count(2)->for($trainer)->create([
        'payout_id' => null,
    ]);

    $owed = $earnings->sum(fn ($e) => (float) $e->net_amount);
    $admin = staffUser();

    $this->actingAs($admin)
        ->post(route('admin.payouts.store', $trainer))
        ->assertRedirect();

    $payout = TrainerPayout::sole();
    expect($payout->amount)->toEqual($owed)
        ->and($payout->status)->toBe('pending')
        ->and($payout->earnings)->toHaveCount(2);

    $this->post(route('admin.payouts.sent', $payout))->assertRedirect();

    expect($payout->fresh())
        ->status->toBe('sent')
        ->processed_by->toBe($admin->id)
        ->sent_at->not->toBeNull();
});

test('a trainer can copy a staff-authored template into their library', function () {
    $staff = staffUser();
    $template = ExerciseProgram::factory()->for($staff)->create([
        'published_at' => now(),
    ]);
    $template->workouts()->create(['title' => 'Day 1', 'position' => 0])
        ->items()->create([
            'exercise_id' => Exercise::factory()->create()->slug,
            'position' => 0,
            'sets' => 3,
            'reps' => 10,
        ]);

    $trainer = Trainer::factory()->create();

    $this->actingAs($trainer->user)
        ->post(route('trainer.programs.copy-template', $template))
        ->assertRedirect();

    $copy = $trainer->user->exercisePrograms()->sole();

    expect($copy->name)->toBe($template->name)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->workouts)->toHaveCount(1)
        ->and($copy->workouts->first()->items)->toHaveCount(1);
});

test('a trainer cannot copy another trainer\'s program', function () {
    $other = Trainer::factory()->create();
    $program = ExerciseProgram::factory()->for($other->user)->create([
        'published_at' => now(),
    ]);

    $this->actingAs(Trainer::factory()->create()->user)
        ->post(route('trainer.programs.copy-template', $program))
        ->assertNotFound();
});
