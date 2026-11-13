<?php

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a member submits a weekly check-in with media and updates it', function () {
    Storage::fake('public');

    $member = User::factory()->withCoach()->create();

    $this->actingAs($member)
        ->post(route('member.check-ins.store'), [
            'weight_kg' => 82.5,
            'note' => 'Felt strong this week.',
            'media' => [UploadedFile::fake()->image('front.jpg')],
        ])
        ->assertRedirect(route('member.check-ins.index'));

    $checkIn = CheckIn::sole();
    expect($checkIn->user_id)->toBe($member->id)
        ->and($checkIn->week_of->toDateString())->toBe(CheckIn::currentWeek()->toDateString())
        ->and((float) $checkIn->weight_kg)->toBe(82.5)
        ->and($checkIn->media()->sole()->kind)->toBe('photo');

    // Same week → the row updates instead of duplicating.
    $this->post(route('member.check-ins.store'), ['weight_kg' => 82.0]);
    expect(CheckIn::count())->toBe(1)
        ->and((float) CheckIn::sole()->weight_kg)->toBe(82.0);
});

test('the member sees the coach reply on their check-in', function () {
    $member = User::factory()->withCoach()->create();
    $checkIn = CheckIn::factory()->for($member)->replied()->create();

    $this->actingAs($member)
        ->get(route('member.check-ins.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current.trainer_reply', $checkIn->trainer_reply));
});

test('the trainer board flags missing and unreplied clients', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();

    $missing = User::factory()->create();
    $awaiting = User::factory()->create();
    $done = User::factory()->create();
    $trainer->clients()->attach([$missing->id, $awaiting->id, $done->id]);

    CheckIn::factory()->for($awaiting)->create();
    CheckIn::factory()->for($done)->replied()->create();

    $this->actingAs($trainerUser)
        ->get(route('trainer.check-ins.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention.0.status', 'missing')
            ->where('attention.1.status', 'awaiting_reply')
            ->where('attention.2.status', 'done')
            ->has('review', 2));
});

test('a trainer replies to their own client only', function () {
    $trainerUser = User::factory()->trainer()->create();
    $trainer = $trainerUser->ensureTrainer();

    $client = User::factory()->create();
    $trainer->clients()->attach($client->id);
    $checkIn = CheckIn::factory()->for($client)->create();

    // Another trainer's client → forbidden.
    $outsider = CheckIn::factory()->create();
    $this->actingAs($trainerUser)
        ->post(route('trainer.check-ins.reply', $outsider), ['reply' => 'Hey'])
        ->assertForbidden();

    $this->post(route('trainer.check-ins.reply', $checkIn), [
        'reply' => 'Great progress — keep the protein up.',
    ])->assertRedirect();

    $checkIn->refresh();
    expect($checkIn->trainer_reply)->toBe('Great progress — keep the protein up.')
        ->and($checkIn->replied_by)->toBe($trainerUser->id)
        ->and($checkIn->replied_at)->not->toBeNull();
});
