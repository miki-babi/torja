<?php

use App\Models\Exercise;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'test-token');
    config()->set('services.telegram.bot_username', 'trainpro_bot');
    config()->set('services.telegram.mini_app_name', 'app');
    config()->set('services.telegram.mini_app_url', 'https://app.test');
    config()->set('services.telegram.webhook_secret', null);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);
});

test('inline queries answer with gif results and deep-link buttons', function () {
    Exercise::factory()->create([
        'name' => 'Barbell Bench Press',
        'slug' => 'barbell-bench-press',
    ]);
    Exercise::factory()->create(['name' => 'Cable Fly']);

    $this->postJson(route('telegram.webhook'), [
        'inline_query' => [
            'id' => 'iq1',
            'query' => 'bench',
            'offset' => '',
            'from' => ['id' => 4242, 'language_code' => 'en'],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerInlineQuery')
        && $request['inline_query_id'] === 'iq1'
        && count($request['results']) === 1
        && $request['results'][0]['type'] === 'gif'
        && $request['results'][0]['id'] === 'barbell-bench-press'
        && $request['results'][0]['title'] === 'Barbell Bench Press'
        && str_contains($request['results'][0]['gif_url'], 'exercises/videos/')
        && str_contains($request['results'][0]['thumbnail_url'], 'exercises/images/')
        && str_contains($request['results'][0]['caption'], '<b>Barbell Bench Press</b>')
        && $request['results'][0]['reply_markup']['inline_keyboard'][0][0]['url']
            === 'https://t.me/trainpro_bot/app?startapp=ex_barbell-bench-press'
        && ! isset($request['button'])
        && ! isset($request['next_offset']));
});

test('inline queries paginate through next_offset', function () {
    Exercise::factory()->count(12)->sequence(
        ...collect(range(1, 12))->map(fn (int $n): array => [
            'name' => sprintf('Press %02d', $n),
            'slug' => sprintf('press-%02d', $n),
        ])->all(),
    )->create();

    $this->postJson(route('telegram.webhook'), [
        'inline_query' => [
            'id' => 'iq1',
            'query' => 'press',
            'offset' => '',
            'from' => ['id' => 4242],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerInlineQuery')
        && count($request['results']) === 10
        && $request['next_offset'] === '10');

    $this->postJson(route('telegram.webhook'), [
        'inline_query' => [
            'id' => 'iq2',
            'query' => 'press',
            'offset' => '10',
            'from' => ['id' => 4242],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerInlineQuery')
        && $request['inline_query_id'] === 'iq2'
        && count($request['results']) === 2
        && ! isset($request['next_offset']));
});

test('an empty query lists the most-logged exercises first', function () {
    Exercise::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
    Exercise::factory()->create(['name' => 'Beta', 'slug' => 'beta']);
    Exercise::factory()->create(['name' => 'Gamma', 'slug' => 'gamma']);

    $session = WorkoutSession::factory()->create();
    WorkoutSet::factory()->create([
        'workout_session_id' => $session->id,
        'exercise_id' => 'beta',
        'set_index' => 0,
    ]);
    WorkoutSet::factory()->create([
        'workout_session_id' => $session->id,
        'exercise_id' => 'beta',
        'set_index' => 1,
    ]);
    WorkoutSet::factory()->create([
        'workout_session_id' => $session->id,
        'exercise_id' => 'gamma',
        'set_index' => 0,
    ]);

    $this->postJson(route('telegram.webhook'), [
        'inline_query' => [
            'id' => 'iq1',
            'query' => '',
            'from' => ['id' => 4242],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerInlineQuery')
        && array_column($request['results'], 'id') === ['beta', 'gamma', 'alpha']
        && ! isset($request['button']));
});

test('amharic speakers see translated exercise names', function () {
    Exercise::factory()->create([
        'name' => 'Barbell Bench Press',
        'name_am' => 'የቤንች ፕሬስ',
        'slug' => 'barbell-bench-press',
    ]);

    $this->postJson(route('telegram.webhook'), [
        'inline_query' => [
            'id' => 'iq1',
            'query' => 'bench',
            'from' => ['id' => 4242, 'language_code' => 'am'],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerInlineQuery')
        && $request['results'][0]['title'] === 'የቤንች ፕሬስ');
});

test('chosen inline results record an exercise.shared event', function () {
    $user = User::factory()->create(['telegram_id' => '4242']);
    $exercise = Exercise::factory()->create(['slug' => 'barbell-bench-press']);

    $this->postJson(route('telegram.webhook'), [
        'chosen_inline_result' => [
            'result_id' => 'barbell-bench-press',
            'from' => ['id' => 4242],
            'query' => 'bench',
            'inline_message_id' => 'msg-abc',
        ],
    ])->assertOk();

    $event = TrackedEvent::query()
        ->where('user_id', $user->id)
        ->where('name', 'exercise.shared')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->subject_id)->toBe($exercise->id)
        ->and($event->properties['query'])->toBe('bench');
});

test('chosen results from unknown telegram users still track', function () {
    Exercise::factory()->create(['slug' => 'barbell-bench-press']);

    $this->postJson(route('telegram.webhook'), [
        'chosen_inline_result' => [
            'result_id' => 'barbell-bench-press',
            'from' => ['id' => 999999],
            'query' => 'bench',
        ],
    ])->assertOk();

    expect(TrackedEvent::query()
        ->where('name', 'exercise.shared')
        ->whereNull('user_id')
        ->exists())->toBeTrue();
});
