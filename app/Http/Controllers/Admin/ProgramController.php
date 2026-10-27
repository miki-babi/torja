<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresPublicImage;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use App\Models\ShortLink;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    use StoresPublicImage;

    /**
     * List all programs with workout/claim counts and share state.
     */
    public function index(): Response
    {
        return Inertia::render('admin/programs/index', [
            'programs' => ExerciseProgram::query()
                ->withCount(['workouts', 'accessGrants'])
                ->latest()
                ->paginate(15)
                ->through(fn (ExerciseProgram $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'price_etb' => $program->price_etb,
                    'is_free' => $program->isFree(),
                    'workouts_count' => $program->workouts_count,
                    'claims_count' => $program->access_grants_count,
                    'share_url' => $program->shareUrl(),
                    'published' => $program->isPublished(),
                ]),
        ]);
    }

    /**
     * Blank program form.
     */
    public function create(): Response
    {
        return Inertia::render('admin/programs/edit', [
            'program' => null,
            'exerciseSummaries' => [],
            ...$this->filterProps(),
        ]);
    }

    /**
     * Create a program and its workouts in one submit.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $program = DB::transaction(function () use ($request, $data): ExerciseProgram {
            $program = $request->user()->exercisePrograms()->create([
                'name' => $data['name'],
                'name_am' => $data['name_am'] ?? null,
                'description' => $data['description'] ?? null,
                'description_am' => $data['description_am'] ?? null,
                'image_path' => $this->storeImage($request, 'programs'),
                'highlights' => $data['highlights'] ?? null,
                'highlights_am' => $data['highlights_am'] ?? null,
                'price_etb' => $data['price_etb'],
                'published_at' => ($data['is_published'] ?? false) ? now() : null,
                'share_token' => ($data['is_published'] ?? false) ? (string) Str::uuid() : null,
            ]);

            $this->syncWorkouts($program, $data['workouts'] ?? []);

            return $program;
        });

        app(Tracker::class)->record('admin.program_created', subject: $program);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program created.'),
        ]);

        return to_route('admin.programs.edit', $program);
    }

    /**
     * Edit form for one program, including its workout tree.
     */
    public function edit(ExerciseProgram $program): Response
    {
        $program->load('workouts.items');

        $exerciseSlugs = $program->workouts
            ->flatMap(fn (ExerciseProgramWorkout $workout) => $workout->items->pluck('exercise_id'))
            ->unique()
            ->all();

        return Inertia::render('admin/programs/edit', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'name_am' => $program->name_am,
                'description' => $program->description,
                'description_am' => $program->description_am,
                'image_url' => $program->image_url,
                'highlights' => $program->highlights ?? [],
                'highlights_am' => $program->highlights_am ?? [],
                'price_etb' => $program->price_etb,
                'share_url' => $program->shareUrl(),
                'published' => $program->isPublished(),
                'workouts' => $program->workouts
                    ->map(fn (ExerciseProgramWorkout $workout): array => [
                        'id' => $workout->id,
                        'title' => $workout->title,
                        'exercises' => $workout->items
                            ->map(fn (ExerciseProgramWorkoutItem $item): array => [
                                'slug' => $item->exercise_id,
                                'sets' => $item->sets,
                                'reps' => $item->reps,
                            ])
                            ->all(),
                    ])
                    ->all(),
            ],
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseSlugs),
            ...$this->filterProps(),
        ]);
    }

    /**
     * Filter options for the exercise picker dialog — same source as the
     * member explore screen.
     *
     * @return array{bodyParts: array<int, array{id: string, label: string, count: int}>, equipment: array<int, string>, levels: array<int, string>}
     */
    private function filterProps(): array
    {
        return [
            'bodyParts' => collect(ExerciseCatalog::BODY_PARTS)
                ->reject(fn (string $part): bool => $part === 'lower arms')
                ->map(fn (string $part): array => $part === 'upper arms'
                    ? [
                        'id' => 'arms',
                        'label' => 'Arms',
                        'count' => count(ExerciseCatalog::idsFor('upper arms'))
                            + count(ExerciseCatalog::idsFor('lower arms')),
                    ]
                    : [
                        'id' => $part,
                        'label' => ExerciseCatalog::label($part),
                        'count' => count(ExerciseCatalog::idsFor($part)),
                    ])
                ->prepend([
                    'id' => '',
                    'label' => 'Full body',
                    'count' => Exercise::count(),
                ])
                ->values()
                ->all(),
            'equipment' => Exercise::query()->distinct()->orderBy('equipment')->pluck('equipment')->all(),
            'levels' => ['beginner', 'intermediate', 'advanced'],
        ];
    }

    /**
     * Persist edits plus the full workout tree in one submit.
     */
    public function update(Request $request, ExerciseProgram $program): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $program, $data): void {
            $program->update([
                'name' => $data['name'],
                'name_am' => $data['name_am'] ?? null,
                'description' => $data['description'] ?? null,
                'description_am' => $data['description_am'] ?? null,
                'image_path' => $this->storeImage($request, 'programs', $program),
                'highlights' => $data['highlights'] ?? null,
                'highlights_am' => $data['highlights_am'] ?? null,
                'price_etb' => $data['price_etb'],
                'published_at' => ($data['is_published'] ?? $program->isPublished()) ? ($program->published_at ?? now()) : null,
                'share_token' => ($data['is_published'] ?? $program->isPublished()) ? ($program->share_token ?? (string) Str::uuid()) : $program->share_token,
            ]);

            $this->syncWorkouts($program, $data['workouts'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program updated.'),
        ]);

        return to_route('admin.programs.edit', $program);
    }

    /**
     * Delete the program (cascades workouts/items/claims).
     */
    public function destroy(ExerciseProgram $program): RedirectResponse
    {
        $program->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program deleted.'),
        ]);

        return to_route('admin.programs.index');
    }

    /**
     * Enable sharing: mints (or reuses) the share token and returns the URL.
     */
    public function share(ExerciseProgram $program): JsonResponse
    {
        $program->update([
            'share_token' => $program->share_token ?? (string) Str::uuid(),
        ]);

        app(Tracker::class)->record('admin.program_shared', subject: $program);

        return response()->json(['share_url' => $program->shareUrl()]);
    }

    /**
     * Disable sharing: clears the token so the public link stops working.
     */
    public function unshare(ExerciseProgram $program): JsonResponse
    {
        $program->update(['share_token' => null]);
        ShortLink::forget($program);

        app(Tracker::class)->record('admin.program_unshared', subject: $program);

        return response()->json(['share_url' => null]);
    }

    /**
     * List the program in the public /programs catalog, minting a share
     * token when needed so the catalog cards have a detail URL.
     */
    public function publish(ExerciseProgram $program): JsonResponse
    {
        $program->update([
            'published_at' => $program->published_at ?? now(),
            'share_token' => $program->share_token ?? (string) Str::uuid(),
        ]);

        app(Tracker::class)->record('admin.program_published', subject: $program);

        return response()->json(['published' => true]);
    }

    /**
     * Remove the program from the public catalog. The share link stays
     * live — publishing and sharing are independent.
     */
    public function unpublish(ExerciseProgram $program): JsonResponse
    {
        $program->update(['published_at' => null]);

        app(Tracker::class)->record('admin.program_unpublished', subject: $program);

        return response()->json(['published' => false]);
    }

    /**
     * Validate the program form payload, including the nested workout tree.
     *
     * @return array{name: string, name_am: string|null, description: string|null, description_am: string|null, price_etb: int, is_published?: bool, highlights?: array<int, string>, highlights_am?: array<int, string>, workouts?: array<int, array{id?: int, title: string, exercises?: array<int, array{slug: string, sets?: int|null, reps?: int|null}>}>}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'name_am' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_am' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'highlights' => ['nullable', 'array', 'max:10'],
            'highlights.*' => ['string', 'max:120'],
            'highlights_am' => ['nullable', 'array', 'max:10'],
            'highlights_am.*' => ['string', 'max:120'],
            'price_etb' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
            'workouts' => ['nullable', 'array', 'max:30'],
            'workouts.*.id' => ['nullable', 'integer'],
            'workouts.*.title' => ['required', 'string', 'max:60'],
            'workouts.*.exercises' => ['nullable', 'array', 'max:30'],
            'workouts.*.exercises.*.slug' => ['required', 'string', 'max:100', Rule::exists('exercises', 'slug')],
            'workouts.*.exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'workouts.*.exercises.*.reps' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }

    /**
     * Replace the program's workout tree with the submitted one: delete
     * removed workouts, upsert the rest in order, and rebuild each workout's
     * items (items carry no stable identity — slug + position only).
     *
     * @param  array<int, array{id?: int, title: string, exercises?: array<int, array{slug: string, sets?: int|null, reps?: int|null}>}>  $workouts
     */
    private function syncWorkouts(ExerciseProgram $program, array $workouts): void
    {
        $keepIds = collect($workouts)->pluck('id')->filter()->all();

        $program->workouts()->whereNotIn('id', $keepIds)->delete();

        foreach ($workouts as $position => $workoutData) {
            $workout = isset($workoutData['id'])
                ? $program->workouts()->where('id', $workoutData['id'])->first()
                : null;

            if ($workout === null) {
                $workout = $program->workouts()->create([
                    'title' => $workoutData['title'],
                    'position' => $position,
                ]);
            } else {
                $workout->update([
                    'title' => $workoutData['title'],
                    'position' => $position,
                ]);
            }

            $workout->items()->delete();
            $workout->items()->createMany(
                collect($workoutData['exercises'] ?? [])
                    ->map(fn (array $item, int $itemPosition): array => [
                        'exercise_id' => $item['slug'],
                        'position' => $itemPosition,
                        'sets' => $item['sets'] ?? null,
                        'reps' => $item['reps'] ?? null,
                    ])
                    ->all(),
            );
        }
    }
}
