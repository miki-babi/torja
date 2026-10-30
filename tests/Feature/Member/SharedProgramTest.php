<?php

use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\Payment;
use App\Models\User;
use App\Services\Programs\ProgramAccessGranter;
use Database\Seeders\LanguageSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function sharedProgram(array $attributes = []): ExerciseProgram
{
    Exercise::factory()->create(['slug' => 'barbell-bench-press', 'name' => 'Barbell Bench Press']);
    Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push Up']);
    Exercise::factory()->create(['slug' => 'squat', 'name' => 'Squat']);

    $program = ExerciseProgram::factory()->create($attributes);

    $push = $program->workouts()->create(['title' => 'Push', 'position' => 0]);
    $push->items()->createMany([
        ['exercise_id' => 'barbell-bench-press', 'position' => 0],
        ['exercise_id' => 'push-up', 'position' => 1],
    ]);

    $legs = $program->workouts()->create(['title' => 'Legs', 'position' => 1]);
    $legs->items()->createMany([
        ['exercise_id' => 'squat', 'position' => 0],
    ]);

    return $program;
}

test('guests can view a shared program with its workouts and exercises', function () {
    $program = sharedProgram(['name' => 'Home Split']);

    $this->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/shared-program')
            ->where('program.name', 'Home Split')
            ->where('program.image_url', null)
            ->where('program.highlights', [])
            ->has('program.workouts', 2)
            ->has('program.workouts.0.exercises', 2)
            ->where('alreadyClaimed', false));
});

test('the shared page exposes the cover image and highlights', function () {
    $program = sharedProgram([
        'image_path' => 'programs/hero.jpg',
        'highlights' => ['Lose weight', 'Six guided days'],
    ]);

    $this->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/shared-program')
            ->where('program.image_url', fn (string $url): bool => str_contains($url, 'programs/hero.jpg'))
            ->where('program.highlights', ['Lose weight', 'Six guided days']));
});

test('the shared page serves Amharic content when the am locale cookie is set', function () {
    $this->seed(LanguageSeeder::class);

    $program = sharedProgram([
        'name' => 'Weight Loss',
        'name_am' => 'የክብደት መቀነስ',
        'description' => 'For men',
        'description_am' => 'ለወንዶች',
        'highlights' => ['Lose weight'],
        'highlights_am' => ['ክብደት ይቀንሱ'],
    ]);

    $this->withCookie('locale', 'am')
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.name', 'የክብደት መቀነስ')
            ->where('program.description', 'ለወንዶች')
            ->where('program.highlights', ['ክብደት ይቀንሱ']));
});

test('missing Amharic content falls back to English under the am locale', function () {
    $this->seed(LanguageSeeder::class);

    $program = sharedProgram([
        'name' => 'Weight Loss',
        'highlights' => ['Lose weight'],
    ]);

    $this->withCookie('locale', 'am')
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.name', 'Weight Loss')
            ->where('program.highlights', ['Lose weight']));
});

test('guests can switch locale via the public endpoint', function () {
    $this->seed(LanguageSeeder::class);

    $this->post(route('locale.set'), ['locale' => 'am'])
        ->assertRedirect()
        ->assertCookie('locale', 'am');

    $this->post(route('locale.set'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

test('unshared programs return 404', function () {
    $program = ExerciseProgram::factory()->unshared()->create();

    $this->get('/shared/programs/not-a-token')->assertNotFound();

    expect($program->shareUrl())->toBeNull();
});

test('guests are sent to login when they try to save', function () {
    $program = sharedProgram();

    $this->post(route('programs.shared.save', $program->share_token))
        ->assertRedirect(route('login'));
});

test('pro members can claim a program which copies its workouts into playlists', function () {
    $program = sharedProgram(['name' => 'Starter']);
    $user = proUser();

    $this->actingAs($user)
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertCreated()
        ->assertJsonPath('claimed', true);

    expect(ExerciseProgramAccess::sole()->exercise_program_id)->toBe($program->id);

    $playlists = $user->exercisePlaylists()->with('items')->get()->keyBy('name');
    expect($playlists->keys()->all())->toBe(['Starter — Legs', 'Starter — Push'])
        ->and($playlists['Starter — Push']->items->pluck('exercise_id')->all())
        ->toBe(['barbell-bench-press', 'push-up'])
        ->and($playlists['Starter — Legs']->items->pluck('exercise_id')->all())->toBe(['squat']);
});

test('claiming a program twice is idempotent', function () {
    $program = sharedProgram();
    $user = proUser();

    $this->actingAs($user)
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertOk();

    expect($user->exercisePlaylists()->count())->toBe(2)
        ->and(ExerciseProgramAccess::count())->toBe(1);
});

test('a claimed program shows as claimed on the shared page', function () {
    $program = sharedProgram();
    $user = User::factory()->create();
    app(ProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('alreadyClaimed', true));
});

test('non-pro members cannot claim a program', function () {
    $program = sharedProgram();

    $this->actingAs(User::factory()->create())
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertForbidden();
});

test('the shared page exposes the pro state and upgrade url', function () {
    $program = sharedProgram();

    $this->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('homeUrl', route('explore.index')));

    $this->actingAs(User::factory()->create())
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isPro', false)
            ->where('proUrl', route('pro.show'))
            ->where('homeUrl', route('member.home')));

    $this->actingAs(proUser())
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('isPro', true));
});

test('a successful legacy program payment still grants access and does not grant pro', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $program = sharedProgram(['price_etb' => 300]);
    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create([
        'status' => 'pending',
        'purpose' => 'program_purchase',
        'payable_type' => $program->getMorphClass(),
        'payable_id' => $program->id,
    ]);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('programs.shared.show', $program->share_token));

    expect($payment->fresh()->isSuccessful())->toBeTrue()
        ->and(ExerciseProgramAccess::sole()->payment_id)->toBe($payment->id)
        ->and($user->exercisePlaylists()->count())->toBe(2)
        // A legacy program purchase does not grant Pro.
        ->and($user->fresh()->isPro())->toBeFalse();
});

test('the webhook also fulfills a legacy program purchase', function () {
    config(['services.chapa.webhook_secret' => 'wh-secret']);

    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $program = sharedProgram(['price_etb' => 300]);
    $payment = Payment::factory()->create([
        'purpose' => 'program_purchase',
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
        ->and(ExerciseProgramAccess::count())->toBe(1)
        ->and($payment->user->exercisePlaylists()->count())->toBe(2);
});

test('a second claim on an already-owned program copies nothing again', function () {
    $program = sharedProgram();
    $user = proUser();
    app(ProgramAccessGranter::class)->grant($user, $program);

    $this->actingAs($user)
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertOk();

    expect($user->exercisePlaylists()->count())->toBe(2)
        ->and(ExerciseProgramAccess::count())->toBe(1);
});

test('playlists copied from a program keep a link back to it', function () {
    $program = sharedProgram();
    $user = proUser();

    $this->actingAs($user)
        ->postJson(route('programs.shared.save', $program->share_token))
        ->assertCreated();

    expect($user->exercisePlaylists)->toHaveCount(2)
        ->and($user->exercisePlaylists->pluck('exercise_program_id')->unique()->all())
        ->toBe([$program->id]);
});

test('the shared page exposes workout ids, set targets and the start url', function () {
    $program = sharedProgram();
    $program->workouts()->where('title', 'Push')->sole()
        ->items()->where('exercise_id', 'barbell-bench-press')
        ->update(['sets' => 4, 'reps' => 8]);

    $this->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('program.workouts.0.id')
            ->has('program.workouts.0.exercises.0.gif_url')
            ->where('program.workouts.0.exercises.0.sets', 4)
            ->where('program.workouts.0.exercises.0.reps', 8)
            ->where('program.workouts.0.exercises.1.sets', null)
            ->where('startUrl', route('programs.shared.start', $program->share_token))
            ->where('todaysSessions', []));
});

test('guests cannot start a workout', function () {
    $program = sharedProgram();
    $workout = $program->workouts()->first();

    $this->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $workout->id])
        ->assertUnauthorized();
});

test('non-pro members cannot start a workout', function () {
    $program = sharedProgram();
    $workout = $program->workouts()->first();

    $this->actingAs(User::factory()->create())
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $workout->id])
        ->assertForbidden();
});

test('starting a workout claims the program and opens a session on that day\'s copy', function () {
    $program = sharedProgram();
    $user = proUser();
    $legs = $program->workouts()->where('title', 'Legs')->sole();

    $response = $this->actingAs($user)
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $legs->id])
        ->assertCreated()
        ->assertJsonPath('claimed', true);

    $playlist = $user->exercisePlaylists()->where('exercise_program_workout_id', $legs->id)->sole();

    expect(ExerciseProgramAccess::count())->toBe(1)
        ->and($user->exercisePlaylists()->count())->toBe(2)
        ->and($playlist->name)->toBe("{$program->name} — Legs")
        ->and($response->json('session.exercise_playlist_id'))->toBe($playlist->id)
        ->and($user->workoutSessions()->count())->toBe(1);
});

test('starting the same workout again resumes today\'s session', function () {
    $program = sharedProgram();
    $user = proUser();
    $workout = $program->workouts()->first();

    $first = $this->actingAs($user)
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $workout->id])
        ->assertCreated()
        ->json('session.id');

    $second = $this->actingAs($user)
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $workout->id])
        ->assertOk()
        ->json('session.id');

    expect($second)->toBe($first)
        ->and($user->workoutSessions()->count())->toBe(1);
});

test('a workout from another program cannot be started through this share link', function () {
    $program = sharedProgram();
    $other = ExerciseProgram::factory()->create();
    $foreign = $other->workouts()->create(['title' => 'Elsewhere', 'position' => 0]);

    $this->actingAs(proUser())
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workout_id');
});

test('the shared page lists today\'s sessions keyed by workout for a claiming member', function () {
    $program = sharedProgram();
    $user = proUser();
    $workout = $program->workouts()->first();

    $this->actingAs($user)
        ->postJson(route('programs.shared.start', $program->share_token), ['workout_id' => $workout->id])
        ->assertCreated();

    $this->actingAs($user)
        ->get(route('programs.shared.show', $program->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alreadyClaimed', true)
            ->has("todaysSessions.{$workout->id}.sets"));
});
