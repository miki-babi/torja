<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('exercises:import-media {dir : Path to the exercise-dataset folder (contains images/ and videos/)}')]
#[Description('Copy exercise GIF demos and thumbnails into storage/app/public/exercises and verify DB rows have their media files')]
class ImportExerciseMedia extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/\\');

        foreach (['images', 'videos'] as $kind) {
            if (! is_dir($dir.DIRECTORY_SEPARATOR.$kind)) {
                $this->components->error("Missing [{$dir}/{$kind}] — pass the exercise-dataset folder that contains images/ and videos/.");

                return self::FAILURE;
            }
        }

        $disk = Storage::disk('public');

        foreach (['images', 'videos'] as $kind) {
            $copied = 0;
            $skipped = 0;

            foreach (glob($dir.DIRECTORY_SEPARATOR.$kind.DIRECTORY_SEPARATOR.'*.*') ?: [] as $file) {
                $target = "exercises/{$kind}/".basename($file);

                if ($disk->exists($target) && $disk->size($target) === filesize($file)) {
                    $skipped++;

                    continue;
                }

                $stream = fopen($file, 'rb');

                if ($stream === false) {
                    $this->components->warn("Could not read [{$file}] — skipped.");

                    continue;
                }

                $disk->put($target, $stream);
                fclose($stream);
                $copied++;
            }

            $this->components->info("{$kind}: {$copied} copied, {$skipped} already present.");
        }

        $missing = Exercise::query()
            ->get(['image_path', 'gif_path'])
            ->flatMap(fn (Exercise $e): array => [$e->image_path, $e->gif_path])
            ->filter(fn (string $path): bool => ! $disk->exists($path))
            ->values();

        if ($missing->isNotEmpty()) {
            $this->components->warn("{$missing->count()} media files referenced by exercises are missing (e.g. {$missing->first()}).");
        } else {
            $this->components->info('All exercise media files are in place.');
        }

        return self::SUCCESS;
    }
}
