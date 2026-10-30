<?php

namespace App\Support;

/**
 * Transliterates English text into Ethiopic script — writes how the English
 * sounds, e.g. "sit up" → "ሲት አፕ". Used for exercise names, which are
 * transliterated rather than translated.
 *
 * Words present in AmharicWordMap::MAP get the curated value; anything else
 * goes through a grapheme-level fallback and is collected in
 * unmappedWords() so the dictionary can be extended.
 */
class AmharicTransliterator
{
    /** Consonant letter/digraph → fidel family, orders 1–7 (ä,u,i,a,e,ɨ,o). */
    private const FAMILIES = [
        'b' => ['በ', 'ቡ', 'ቢ', 'ባ', 'ቤ', 'ብ', 'ቦ'],
        'd' => ['ደ', 'ዱ', 'ዲ', 'ዳ', 'ዴ', 'ድ', 'ዶ'],
        'f' => ['ፈ', 'ፉ', 'ፊ', 'ፋ', 'ፌ', 'ፍ', 'ፎ'],
        'g' => ['ገ', 'ጉ', 'ጊ', 'ጋ', 'ጌ', 'ግ', 'ጎ'],
        'h' => ['ሀ', 'ሁ', 'ሂ', 'ሃ', 'ሄ', 'ህ', 'ሆ'],
        'j' => ['ጀ', 'ጁ', 'ጂ', 'ጃ', 'ጄ', 'ጅ', 'ጆ'],
        'k' => ['ከ', 'ኩ', 'ኪ', 'ካ', 'ኬ', 'ክ', 'ኮ'],
        'l' => ['ለ', 'ሉ', 'ሊ', 'ላ', 'ሌ', 'ል', 'ሎ'],
        'm' => ['መ', 'ሙ', 'ሚ', 'ማ', 'ሜ', 'ም', 'ሞ'],
        'n' => ['ነ', 'ኑ', 'ኒ', 'ና', 'ኔ', 'ን', 'ኖ'],
        'p' => ['ፐ', 'ፑ', 'ፒ', 'ፓ', 'ፔ', 'ፕ', 'ፖ'],
        'r' => ['ረ', 'ሩ', 'ሪ', 'ራ', 'ሬ', 'ር', 'ሮ'],
        's' => ['ሰ', 'ሱ', 'ሲ', 'ሳ', 'ሴ', 'ስ', 'ሶ'],
        't' => ['ተ', 'ቱ', 'ቲ', 'ታ', 'ቴ', 'ት', 'ቶ'],
        'v' => ['ቨ', 'ቩ', 'ቪ', 'ቫ', 'ቬ', 'ቭ', 'ቮ'],
        'w' => ['ወ', 'ዉ', 'ዊ', 'ዋ', 'ዌ', 'ው', 'ዎ'],
        'y' => ['የ', 'ዩ', 'ዪ', 'ያ', 'ዬ', 'ይ', 'ዮ'],
        'z' => ['ዘ', 'ዙ', 'ዚ', 'ዛ', 'ዜ', 'ዝ', 'ዞ'],
        'sh' => ['ሸ', 'ሹ', 'ሺ', 'ሻ', 'ሼ', 'ሽ', 'ሾ'],
        'ch' => ['ቸ', 'ቹ', 'ቺ', 'ቻ', 'ቼ', 'ች', 'ቾ'],
        'th' => ['ሰ', 'ሱ', 'ሲ', 'ሳ', 'ሴ', 'ስ', 'ሶ'],
        'ph' => ['ፈ', 'ፉ', 'ፊ', 'ፋ', 'ፌ', 'ፍ', 'ፎ'],
    ];

    /** Vowel letter → family order index (0–6) used on a preceding consonant. */
    private const VOWEL_ORDER = [
        'a' => 3,
        'e' => 4,
        'i' => 2,
        'o' => 6,
        'u' => 1,
    ];

    /** Standalone vowel digraphs/letters at word start or after a vowel. */
    private const VOWEL_CHARS = [
        'a' => 'አ',
        'e' => 'ኤ',
        'i' => 'ኢ',
        'o' => 'ኦ',
        'u' => 'ኡ',
    ];

    /** @var array<string, true> Words that fell through to the grapheme fallback. */
    private static array $unmapped = [];

    /**
     * The catalog display name: transliteration with the English original in
     * brackets — "barbell bench press" → "ባርቤል ቤንች ፕሬስ (barbell bench press)".
     */
    public static function displayName(string $english): string
    {
        return self::phrase($english).' ('.$english.')';
    }

    /**
     * Transliterate a full phrase. Parenthesised text is transliterated in
     * place, digits and symbols pass through, hyphens become spaces.
     */
    public static function phrase(string $english): string
    {
        $segments = preg_split('/([a-zA-Z]+)/', $english, -1, PREG_SPLIT_DELIM_CAPTURE);

        $out = '';
        foreach ($segments as $i => $segment) {
            if ($segment === '') {
                continue;
            }

            if ($i % 2 === 1) {
                $out .= self::word($segment);
            } else {
                $out .= str_replace('-', ' ', $segment);
            }
        }

        $out = (string) preg_replace('/\s+/', ' ', $out);
        $out = str_replace(['( ', ' )'], ['(', ')'], $out);

        return trim($out);
    }

    /**
     * One word: curated map first, phonetic fallback otherwise.
     */
    public static function word(string $word): string
    {
        $lower = strtolower($word);

        return AmharicWordMap::MAP[$lower] ?? self::fallback($lower);
    }

    /**
     * Words that hit the grapheme fallback since the last reset.
     *
     * @return array<int, string>
     */
    public static function unmappedWords(): array
    {
        return array_keys(self::$unmapped);
    }

    public static function resetUnmapped(): void
    {
        self::$unmapped = [];
    }

    /**
     * Best-effort grapheme → fidel transliteration for words missing from the
     * map. Approximate by design; every call is reported via unmappedWords().
     */
    private static function fallback(string $word): string
    {
        self::$unmapped[$word] = true;

        $out = '';
        $i = 0;
        $len = strlen($word);

        while ($i < $len) {
            $consonants = self::consumeConsonants($word, $i, $len);

            if ($consonants === []) {
                $out .= self::standaloneVowel($word, $i, $len);

                continue;
            }

            [$order, $i] = self::consumeVowel($word, $i, $len);

            $out .= self::emit($consonants, $order);
        }

        return $out;
    }

    /**
     * Consume a consonant cluster; digraphs match longest-first. 'c' softens
     * to ሰ before e/i/y, 'g' softens to ጀ before e/i.
     *
     * @return array<int, string> family keys consumed (e.g. ['s','t','r'])
     */
    private static function consumeConsonants(string $word, int &$i, int $len): array
    {
        $consonants = [];

        while ($i < $len && ! self::isVowel($word[$i])) {
            $rest = substr($word, $i);

            foreach (['tch', 'sch', 'sh', 'ch', 'th', 'ph', 'ck', 'qu', 'gh', 'kn', 'wr'] as $digraph) {
                if (str_starts_with($rest, $digraph)) {
                    $family = match ($digraph) {
                        'tch', 'ch' => 'ch',
                        'sch' => 'sk',
                        'sh' => 'sh',
                        'th' => 'th',
                        'ph' => 'ph',
                        'ck', 'qu' => 'k',
                        'gh' => 'g',
                        'kn', 'wr' => $digraph === 'kn' ? 'n' : 'r',
                    };

                    if ($family === 'sk') {
                        $consonants[] = 's';
                        $consonants[] = 'k';
                    } else {
                        $consonants[] = $family;
                    }

                    $i += strlen($digraph);

                    continue 2;
                }
            }

            $ch = $word[$i];
            $i++;

            if ($ch === 'x') {
                $consonants[] = 'k';
                $consonants[] = 's';

                continue;
            }

            if ($ch === 'q') {
                $consonants[] = 'k';

                continue;
            }

            if ($ch === 'c') {
                $next = $word[$i] ?? '';
                $consonants[] = in_array($next, ['e', 'i', 'y'], true) ? 's' : 'k';

                continue;
            }

            if ($ch === 'g') {
                $next = $word[$i] ?? '';
                $consonants[] = in_array($next, ['e', 'i', 'y'], true) ? 'j' : 'g';

                continue;
            }

            if (isset(self::FAMILIES[$ch])) {
                $consonants[] = $ch;
            }
        }

        return $consonants;
    }

    /**
     * Consume a vowel run and return the family order it implies, or null for
     * a silent trailing 'e'.
     *
     * @return array{0: int|null, 1: int} [order, new position]
     */
    private static function consumeVowel(string $word, int $i, int $len): array
    {
        $rest = substr($word, $i);
        $vowel = null;

        foreach (['ee', 'ea', 'ie', 'oo', 'ou', 'ow', 'au', 'aw', 'ai', 'ay', 'ey', 'oa', 'oe'] as $digraph) {
            if (str_starts_with($rest, $digraph)) {
                $vowel = $digraph;
                break;
            }
        }

        $vowel ??= $rest[0] ?? '';

        if ($vowel === '') {
            return [null, $i];
        }

        $i += strlen($vowel);

        $order = match ($vowel) {
            'ee', 'ea', 'ie' => 2,
            'oo', 'ou', 'ow' => 1,
            'au', 'aw', 'oa', 'oe' => 6,
            'ai', 'ay', 'ey' => 4,
            default => self::VOWEL_ORDER[$vowel] ?? 3,
        };

        // Silent trailing 'e' (and 'es' handled by caller as consonant 's').
        if ($vowel === 'e' && $i >= $len) {
            return [null, $i];
        }

        return [$order, $i];
    }

    /**
     * Emit a consonant cluster + optional vowel: cluster consonants before the
     * last take the 6th order, the last takes the vowel's order.
     *
     * @param  array<int, string>  $consonants
     */
    private static function emit(array $consonants, ?int $order): string
    {
        $out = '';
        $last = count($consonants) - 1;

        foreach ($consonants as $idx => $family) {
            $fidel = self::FAMILIES[$family];

            $out .= $idx === $last && $order !== null
                ? $fidel[$order]
                : $fidel[5];
        }

        return $out;
    }

    /**
     * Emit a vowel that stands alone (word start or following another vowel).
     */
    private static function standaloneVowel(string $word, int &$i, int $len): string
    {
        $rest = substr($word, $i);

        foreach (['ee', 'ea', 'oo', 'ou', 'ow', 'ai', 'ay', 'au', 'oi', 'ey'] as $digraph) {
            if (str_starts_with($rest, $digraph)) {
                $i += strlen($digraph);

                return match ($digraph) {
                    'ee', 'ea' => 'ኢ',
                    'oo', 'ou' => 'ኡ',
                    'ow', 'au' => 'አው',
                    'ai', 'ay' => 'አይ',
                    'oi' => 'ኦይ',
                    'ey' => 'ኤይ',
                };
            }
        }

        $ch = $word[$i];
        $i++;

        return self::VOWEL_CHARS[$ch] ?? 'አ';
    }

    private static function isVowel(string $ch): bool
    {
        return in_array($ch, ['a', 'e', 'i', 'o', 'u'], true);
    }
}
