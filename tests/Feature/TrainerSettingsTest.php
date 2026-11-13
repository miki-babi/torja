<?php

use App\Models\CheckIn;
use App\Models\ClientPlan;
use App\Models\Trainer;
use App\Models\TrainerPlan;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function settingsTrainer(): User
{
    $user = User::factory()->trainer()->create();
    $user->ensureTrainer();

    return $user;
}

test('the settings screen renders with reminder preferences', function () {
    $this->actingAs(settingsTrainer())
        ->get(route('trainer.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('trainer/settings')
            ->where('reminders.enabled', true));
});

test('settings persist reminder toggle and quiet hours', function () {
    $user = settingsTrainer();

    $this->actingAs($user)->patch(route('trainer.settings.update'), [
        'reminders_enabled' => false,
        'quiet_hours_start' => '21:30',
        'quiet_hours_end' => '07:00',
    ])->assertRedirect();

    $trainer = $user->trainer()->first();

    expect($trainer->reminders_enabled)->toBeFalse()
        ->and(substr((string) $trainer->quiet_hours_start, 0, 5))->toBe('21:30')
        ->and(substr((string) $trainer->quiet_hours_end, 0, 5))->toBe('07:00');
});

test('quiet hours must be set as a pair', function () {
    $this->actingAs(settingsTrainer())
        ->patch(route('trainer.settings.update'), [
            'reminders_enabled' => true,
            'quiet_hours_start' => '22:00',
        ])->assertSessionHasErrors('quiet_hours_end');
});

test('a disabled trainer bot sends no client reminders', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $trainer = Trainer::factory()->withBot()->create(['reminders_enabled' => false]);
    $client = User::factory()->withTelegram()->create();
    $trainer->clients()->attach($client->id);
    WorkoutSession::factory()->for($client)->create(['date' => now()->subDays(3)->toDateString()]);

    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertNothingSent();
});

test('quiet hours suppress reminders for that trainer only', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    // Inside the 22:00 → 07:00 quiet window at 23:00.
    $this->travelTo(now()->setTime(23, 0));

    $quiet = Trainer::factory()->withBot()->create([
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
    ]);
    $quietClient = User::factory()->withTelegram()->create();
    $quiet->clients()->attach($quietClient->id);
    WorkoutSession::factory()->for($quietClient)->create(['date' => now()->subDays(3)->toDateString()]);

    $loud = Trainer::factory()->withBot()->create();
    $loudClient = User::factory()->withTelegram()->create();
    $loud->clients()->attach($loudClient->id);
    WorkoutSession::factory()->for($loudClient)->create(['date' => now()->subDays(3)->toDateString()]);

    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['chat_id'] === $loudClient->telegram_chat_id);
});

test('the dashboard surfaces needs-attention counts', function () {
    $user = settingsTrainer();
    $trainer = $user->trainer()->first();

    $checked = User::factory()->create();
    $missing = User::factory()->create();
    $expiring = User::factory()->create();
    $trainer->clients()->attach([$checked->id, $missing->id, $expiring->id]);

    CheckIn::factory()->for($checked)->create();

    $plan = TrainerPlan::factory()->for($trainer)->create();
    ClientPlan::factory()->create([
        'trainer_plan_id' => $plan->id,
        'user_id' => $expiring->id,
        'ends_at' => now()->addDays(3),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('trainer.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('needsAttention.missing_check_ins', 2)
            ->where('needsAttention.awaiting_replies', 1)
            ->where('needsAttention.expiring_plans', 1));
});
