<?php

namespace App\Support;

use App\Models\Exercise;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Read API over the exercises table — the imported exercise dataset.
 * Playlist items and logged sets store exercise slugs; this resolves slugs to
 * body parts and summary payloads, cached in two maps. Ids from the old
 * hardcoded catalog resolve through LEGACY_SLUGS.
 */
class ExerciseCatalog
{
    /** All dataset body parts, in display order. */
    public const BODY_PARTS = [
        'chest',
        'back',
        'shoulders',
        'upper arms',
        'lower arms',
        'waist',
        'upper legs',
        'lower legs',
        'cardio',
        'neck',
    ];

    /** Explore-filter ids that expand to multiple dataset body parts. */
    public const GROUPED_PARTS = [
        'arms' => ['upper arms', 'lower arms'],
    ];

    /** Body parts the daily workout generator rotates over (neck excluded). */
    public const FOCUS_BODY_PARTS = [
        'chest',
        'back',
        'shoulders',
        'upper arms',
        'lower arms',
        'waist',
        'upper legs',
        'lower legs',
        'cardio',
    ];

    /**
     * Ids from the previous hardcoded catalog → dataset slugs.
     *
     * @var array<string, string>
     */
    public const LEGACY_SLUGS = [
        'bench-press' => 'barbell-bench-press',
        'incline-db-press' => 'dumbbell-incline-bench-press',
        'push-up' => 'push-up',
        'cable-fly' => 'cable-standing-up-straight-crossovers',
        'deadlift' => 'barbell-deadlift',
        'pull-up' => 'pull-up',
        'bent-over-row' => 'barbell-bent-over-row',
        'lat-pulldown' => 'cable-lat-pulldown-full-range-of-motion',
        'overhead-press' => 'dumbbell-standing-overhead-press',
        'lateral-raise' => 'dumbbell-lateral-raise',
        'rear-delt-fly' => 'barbell-rear-delt-raise',
        'arnold-press' => 'dumbbell-arnold-press',
        'bicep-curl' => 'dumbbell-biceps-curl',
        'tricep-dip' => 'triceps-dip',
        'hammer-curl' => 'dumbbell-hammer-curl',
        'rope-pushdown' => 'cable-pushdown-with-rope-attachment',
        'back-squat' => 'barbell-full-squat',
        'romanian-deadlift' => 'barbell-romanian-deadlift',
        'walking-lunge' => 'walking-lunge',
        'leg-press' => 'sled-45v-leg-press',
    ];

    private const CACHE_KEY_SLUG_PART = 'exercise_catalog.slug_to_body_part';

    private const CACHE_KEY_PART_SLUGS = 'exercise_catalog.part_to_slugs';

    /**
     * Body parts the daily generator rotates over.
     *
     * @return array<int, string>
     */
    public static function muscles(): array
    {
        return self::FOCUS_BODY_PARTS;
    }

    /**
     * Display label for a body part key.
     */
    public static function label(string $bodyPart): string
    {
        return Str::headline($bodyPart);
    }

    /**
     * Exercise slugs belonging to a body part, ordered by name.
     *
     * @return array<int, string>
     */
    public static function idsFor(string $bodyPart): array
    {
        return self::partToSlugs()[$bodyPart] ?? [];
    }

    /**
     * The body part an exercise id belongs to, or null for unknown ids.
     */
    public static function muscleFor(string $exerciseId): ?string
    {
        return self::slugToPart()[self::resolve($exerciseId)] ?? null;
    }

    /**
     * Whether the id exists in the catalog (legacy ids included).
     */
    public static function has(string $exerciseId): bool
    {
        return isset(self::slugToPart()[self::resolve($exerciseId)]);
    }

    /**
     * The canonical slug for an id — legacy catalog ids map to dataset slugs.
     */
    public static function resolve(string $exerciseId): string
    {
        return self::LEGACY_SLUGS[$exerciseId] ?? $exerciseId;
    }

    /**
     * Every known exercise slug.
     *
     * @return array<int, string>
     */
    public static function allIds(): array
    {
        return array_keys(self::slugToPart());
    }

    /**
     * Summary payloads keyed by the requested ids (legacy ids included), for
     * rendering exercise names in playlists and sessions.
     *
     * @param  array<int, string>  $exerciseIds
     * @return array<string, array{name: string, detail: string}>
     */
    public static function namesFor(array $exerciseIds): array
    {
        $resolved = collect($exerciseIds)->mapWithKeys(
            fn (string $id): array => [$id => self::resolve($id)],
        );

        $exercises = Exercise::query()
            ->whereIn('slug', $resolved->values()->unique()->all())
            ->get(['slug', 'name', 'equipment', 'target'])
            ->keyBy('slug');

        return $resolved
            ->map(fn (string $slug): ?array => $exercises->has($slug)
                ? ['name' => $exercises[$slug]->name, 'detail' => $exercises[$slug]->detail()]
                : null)
            ->filter()
            ->all();
    }

    /**
     * Drop the cached lookup maps (after seeding or in tests).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY_SLUG_PART);
        Cache::forget(self::CACHE_KEY_PART_SLUGS);
    }

    /**
     * slug → body part map for every exercise.
     *
     * @return array<string, string>
     */
    private static function slugToPart(): array
    {
        return Cache::memo()->rememberForever(
            self::CACHE_KEY_SLUG_PART,
            fn (): array => Exercise::query()->pluck('body_part', 'slug')->all(),
        );
    }

    /**
     * body part → name-ordered slugs.
     *
     * @return array<string, array<int, string>>
     */
    private static function partToSlugs(): array
    {
        return Cache::memo()->rememberForever(
            self::CACHE_KEY_PART_SLUGS,
            function (): array {
                $parts = [];

                foreach (self::BODY_PARTS as $part) {
                    $parts[$part] = Exercise::query()
                        ->where('body_part', $part)
                        ->orderBy('name')
                        ->pluck('slug')
                        ->all();
                }

                return $parts;
            },
        );
    }
}
