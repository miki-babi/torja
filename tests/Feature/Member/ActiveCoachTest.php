<?php

use App\Models\ExerciseProgram;
use App\Models\Trainer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('active coach falls back to the most recently linked trainer', function () {
    $member = User::factory()->create();
    [$older, $newer] = Trainer::factory(2)->create()->all();

    $older->clients()->attach($member->id);
    $newer->clients()->attach($member->id);
    $older->clients()->updateExistingPivot($member->id, ['created_at' => now()->subDays(3)]);

    expect($member->activeCoach()?->id)->toBe($newer->id);
});

test('last open wins over link recency', function () {
    $member = User::factory()->create();
    [$first, $second] = Trainer::factory(2)->create()->all();

    $first->clients()->attach($member->id);
    $second->clients()->attach($member->id);
    $first->markClientOpened($member);

    expect($member->activeCoach()?->id)->toBe($first->id);
});

test('visiting /t/{slug} marks the client as opened through that coach', function () {
    $trainer = Trainer::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->get("/t/{$trainer->slug}");

    $pivot = $member->trainers()->where('trainers.id', $trainer->id)->first()?->pivot;

    expect($pivot?->last_opened_at)->not->toBeNull();
});

test('home exposes the coach programs the member holds', function () {
    $member = User::factory()->create();
    $trainer = Trainer::factory()->create();
    $trainer->clients()->attach($member->id);

    $program = ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Coach Shred',
    ]);
    $member->programAccess()->create(['exercise_program_id' => $program->id]);

    $this->actingAs($member)
        ->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('coachPrograms', 1)
            ->where('coachPrograms.0.name', 'Coach Shred'));
});

test('home hides coach programs from other authors', function () {
    $member = User::factory()->create();
    $trainer = Trainer::factory()->create();
    $trainer->clients()->attach($member->id);

    $others = ExerciseProgram::factory()->create(['name' => 'Platform Plan']);
    $member->programAccess()->create(['exercise_program_id' => $others->id]);

    $this->actingAs($member)
        ->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('coachPrograms', []));
});

test('explore exposes the coach published programs', function () {
    $member = User::factory()->create();
    $trainer = Trainer::factory()->create();
    $trainer->clients()->attach($member->id);

    ExerciseProgram::factory()->published()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Coach Cut',
    ]);
    ExerciseProgram::factory()->published()->create(['name' => 'Global Plan']);

    $this->actingAs($member)
        ->get(route('explore.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('coachPrograms', 1)
            ->where('coachPrograms.0.name', 'Coach Cut'));
});
