<?php

use App\Support\AmharicTransliterator;

beforeEach(fn () => AmharicTransliterator::resetUnmapped());

test('known words come from the curated map', function () {
    expect(AmharicTransliterator::word('barbell'))->toBe('ባርቤል')
        ->and(AmharicTransliterator::word('up'))->toBe('አፕ')
        ->and(AmharicTransliterator::word('press'))->toBe('ፕሬስ');
});

test('phrases transliterate word by word', function () {
    expect(AmharicTransliterator::phrase('sit up'))->toBe('ሲት አፕ')
        ->and(AmharicTransliterator::phrase('barbell bench press'))->toBe('ባርቤል ቤንች ፕሬስ');
});

test('hyphens between words become spaces', function () {
    expect(AmharicTransliterator::phrase('sit-up'))->toBe('ሲት አፕ')
        ->and(AmharicTransliterator::phrase('v-bar'))->toBe('ቪ ባር');
});

test('parenthesised qualifiers are transliterated in place', function () {
    expect(AmharicTransliterator::phrase('sit up (male)'))->toBe('ሲት አፕ (ሜል)');
});

test('digits and symbols pass through unchanged', function () {
    expect(AmharicTransliterator::phrase('3/4 sit-up'))->toBe('3/4 ሲት አፕ')
        ->and(AmharicTransliterator::phrase('45° side bend'))->toBe('45° ሳይድ ቤንድ');
});

test('non-latin characters pass through unchanged', function () {
    expect(AmharicTransliterator::phrase('sled 45в° leg press'))->toBe('ስሌድ 45в° ሌግ ፕሬስ');
});

test('display name appends the english original in brackets', function () {
    expect(AmharicTransliterator::displayName('sit up'))->toBe('ሲት አፕ (sit up)')
        ->and(AmharicTransliterator::displayName('3/4 sit-up'))->toBe('3/4 ሲት አፕ (3/4 sit-up)');
});

test('unknown words fall back to ethiopic output and are reported', function () {
    AmharicTransliterator::resetUnmapped();

    $result = AmharicTransliterator::word('kettlebellx');

    expect($result)->toMatch('/[\x{1200}-\x{137F}]/u')
        ->and(AmharicTransliterator::unmappedWords())->toBe(['kettlebellx']);
});

test('mapped words are not reported as unmapped', function () {
    AmharicTransliterator::resetUnmapped();
    AmharicTransliterator::phrase('barbell bench press');

    expect(AmharicTransliterator::unmappedWords())->toBeEmpty();
});
