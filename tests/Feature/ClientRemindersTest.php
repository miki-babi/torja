<?php

use App\Models\CheckIn;
use App\Models\ClientPlan;
use App\Models\Trainer;
use App\Models\TrainerPlan;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Http;

function coachedTelegramClient(Trainer $trainer): User
{
    $client = User::factory()->withTelegram()->create();
    $trainer->clients()->attach($client->id);

    return $client;
}

test('a lapsed client gets a workout reminder through the trainer bot', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $trainer = Trainer::factory()->withBot()->create();
    $client = coachedTelegramClient($trainer);
    WorkoutSession::factory()->for($client)->create(['date' => now()->subDays(3)->toDateString()]);

    // A client who trained yesterday is not nudged.
    $active = coachedTelegramClient($trainer);
    WorkoutSession::factory()->for($active)->create(['date' => now()->subDay()->toDateString()]);

    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && $request['chat_id'] === $client->telegram_chat_id);
});

test('the check-in reminder only fires on Sunday for missing check-ins', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $trainer = Trainer::factory()->withBot()->create();
    $client = coachedTelegramClient($trainer);

    // Monday → nothing.
    $this->travelTo(now()->startOfWeek()->addDay()->setTime(18, 0));
    $this->artisan('reminders:send')->assertSuccessful();
    Http::assertNothingSent();

    // Sunday, no check-in → one nudge.
    $this->travelTo(now()->startOfWeek()->addDays(6)->setTime(18, 0));
    $this->artisan('reminders:send')->assertSuccessful();
    Http::assertSentCount(1);

    // Sunday with a check-in already sent → quiet.
    CheckIn::factory()->for($client)->create();
    $this->artisan('reminders:send')->assertSuccessful();
    Http::assertSentCount(1);
});

test('an expiring plan pings the client and the opt-out flag silences everything', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $trainer = Trainer::factory()->withBot()->create();
    $plan = TrainerPlan::factory()->for($trainer)->create();
    $client = coachedTelegramClient($trainer);
    ClientPlan::factory()->for($plan, 'plan')->for($client)->create([
        'ends_at' => now()->addDay(),
    ]);

    $this->artisan('reminders:send')->assertSuccessful();
    Http::assertSentCount(1);

    // Opted-out client gets nothing.
    $client->update(['reminders_enabled' => false]);
    $this->artisan('reminders:send')->assertSuccessful();
    Http::assertSentCount(1);
});

test('the member can toggle reminders off and on', function () {
    $member = User::factory()->withCoach()->create();

    $this->actingAs($member)
        ->patchJson(route('member.reminders.update'), ['enabled' => false])
        ->assertOk()
        ->assertJson(['reminders_enabled' => false]);

    expect($member->fresh()->reminders_enabled)->toBeFalse();

    $this->patchJson(route('member.reminders.update'), ['enabled' => true])
        ->assertOk();
    expect($member->fresh()->reminders_enabled)->toBeTrue();
});
