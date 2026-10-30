<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Support\AmharicTransliterator;
use App\Support\ExerciseCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('exercises:transliterate-names {--write : Persist changes to exercises.json and the exercises table (default: dry-run report)} {--path= : Dataset file (defaults to database/data/exercises.json)}')]
#[Description('Regenerate exercises name_am as English-in-Amharic transliteration with the original in brackets')]
class TransliterateExerciseNames extends Command
{
    /**
     * Dry-run shows a sample diff plus the unmapped-word report; --write
     * rewrites the JSON (keeping it authoritative for reseeds) and updates
     * matching exercises rows by source_id.
     */
    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/exercises.json');

        if (! is_file($path)) {
            $this->components->error("Dataset not found at [{$path}].");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("Dataset at [{$path}] is not a JSON array.");
        }

        AmharicTransliterator::resetUnmapped();

        $changed = 0;
        $unchanged = 0;
        $sample = [];

        foreach ($data as $index => $row) {
            $nameAm = AmharicTransliterator::displayName($row['name']);

            if (($row['name_am'] ?? null) === $nameAm) {
                $unchanged++;
            } else {
                $changed++;

                if (count($sample) < 25) {
                    $sample[] = [$row['name'], $nameAm];
                }
            }

            $data[$index]['name_am'] = $nameAm;
            $data[$index]['translation_status'] = 'transliterated';
        }

        if ($sample !== []) {
            $this->table(['name', 'name_am'], $sample);
            if ($changed > count($sample)) {
                $this->components->info('…and '.($changed - count($sample)).' more.');
            }
        }

        $this->components->info("{$changed} changed, {$unchanged} unchanged of ".count($data).' exercises.');

        $unmapped = AmharicTransliterator::unmappedWords();
        if ($unmapped !== []) {
            $this->components->warn('Words missing from AmharicWordMap (grapheme fallback used): '.implode(', ', $unmapped));
        }

        if (! $this->option('write')) {
            $this->components->info('Dry run — pass --write to persist.');

            return self::SUCCESS;
        }

        $contents = self::rewriteColumn($contents, $data, 'name_am');
        $contents = self::rewriteColumn($contents, $data, 'translation_status');
        file_put_contents($path, $contents);

        $dbUpdated = 0;
        $updates = collect($data)->mapWithKeys(
            fn (array $row): array => [$row['id'] => $row['name_am']],
        );

        Exercise::query()
            ->select(['id', 'source_id', 'name_am', 'translation_status'])
            ->chunkById(200, function ($exercises) use ($updates, &$dbUpdated): void {
                foreach ($exercises as $exercise) {
                    $nameAm = $updates->get($exercise->source_id);

                    if ($nameAm === null
                        || ($exercise->name_am === $nameAm && $exercise->translation_status === 'transliterated')) {
                        continue;
                    }

                    Exercise::whereKey($exercise->id)->update([
                        'name_am' => $nameAm,
                        'translation_status' => 'transliterated',
                    ]);
                    $dbUpdated++;
                }
            });

        ExerciseCatalog::flush();

        $this->components->info("Wrote {$path}; updated {$dbUpdated} exercise rows.");

        return self::SUCCESS;
    }

    /**
     * Replace the values of one JSON key in place, in row order, leaving all
     * other formatting untouched. Throws if the file structure doesn't match
     * one string value per row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function rewriteColumn(string $contents, array $rows, string $key): string
    {
        $index = -1;
        $result = (string) preg_replace_callback(
            '/([{,]\s*"'.preg_quote($key, '/').'"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/',
            function (array $m) use (&$index, $rows, $key): string {
                $index++;

                return $m[1].json_encode($rows[$index][$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $contents,
        );

        if ($index + 1 !== count($rows)) {
            throw new RuntimeException('Expected '.count($rows)." \"{$key}\" values in the dataset, found ".($index + 1).'.');
        }

        return $result;
    }
}
