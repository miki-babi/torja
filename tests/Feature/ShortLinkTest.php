<?php

use App\Models\ExercisePlaylist;
use App\Models\ExerciseProgram;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a program share_url is a short link that resolves to the program page', function () {
    $program = ExerciseProgram::factory()->create(['share_token' => 'prog-token-123']);

    $url = $program->shareUrl();

    expect($url)->toStartWith(url('/s/'))
        ->and(Str::length((string) Str::after($url, url('/s/'))))->toBeLessThan(20);

    $this->get($url)
        ->assertRedirect(route('programs.shared.show', 'prog-token-123')
            .'?'.Arr::query([
                'ref' => (string) $program->user_id,
                'utm_source' => 'program',
                'utm_medium' => 'share',
                'utm_content' => 'exercise_program',
            ]));
});

test('resolving a short link counts clicks', function () {
    $link = ShortLink::factory()->create(['url' => route('home')]);

    $this->get(route('links.resolve', $link->code))->assertRedirect(route('home'));

    expect($link->fresh()->clicks)->toBe(1);

    $this->get(route('links.resolve', $link->code));

    expect($link->fresh()->clicks)->toBe(2);
});

test('sharing twice returns the same short link', function () {
    $program = ExerciseProgram::factory()->create(['share_token' => 'stable-token']);

    expect($program->shareUrl())->toBe($program->shareUrl())
        ->and(ShortLink::query()->where('linkable_type', ExerciseProgram::class)->count())->toBe(1);
});

test('playlist share links are shortened too', function () {
    $playlist = ExercisePlaylist::factory()->create(['share_token' => 'pl-token-456']);

    $url = $playlist->shareUrl();

    expect($url)->toStartWith(url('/s/'));

    $this->get($url)->assertRedirectContains('shared/playlists/pl-token-456');
});

test('unknown codes 404', function () {
    $this->get(route('links.resolve', 'nope1234'))->assertNotFound();
});

test('shareUrl stays null without a token', function () {
    $program = ExerciseProgram::factory()->create(['share_token' => null]);

    expect($program->shareUrl())->toBeNull()
        ->and(ShortLink::count())->toBe(0);
});
