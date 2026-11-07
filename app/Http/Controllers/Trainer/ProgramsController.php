<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Admin\Concerns\StoresPublicImage;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use App\Models\ShortLink;
use App\Services\Programs\ProgramAccessGranter;
use App\Services\Trainer\ClientNotifier;
use App\Support\ExerciseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trainer-authored workout programs: CRUD, shareable claim links, and
 * direct assignment to linked clients (via the same idempotent access
 * granter the share/checkout flows use). Trainer programs never publish to
 * the global /explore catalog.
 */
class ProgramsController extends Controller
{
    use StoresPublicImage;

    /**
     * List the trainer's programs with workout/claim counts and share state.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('trainer/programs/index', [
            'programs' => $request->user()->exercisePrograms()
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
                ]),
        ]);
    }

    /**
     * Blank program form.
     */
    public function create(): Response
    {
        return Inertia::render('trainer/programs/edit', [
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
            ]);

            $this->syncWorkouts($program, $data['workouts'] ?? []);

            return $program;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program created.'),
        ]);

        return to_route('trainer.programs.edit', $program);
    }

    /**
     * Edit form for one program, including its workout tree.
     */
    public function edit(Request $request, ExerciseProgram $program): Response
    {
        $this->authorizeOwner($request, $program);

        $program->load('workouts.items');

        $exerciseSlugs = $program->workouts
            ->flatMap(fn (ExerciseProgramWorkout $workout) => $workout->items->pluck('exercise_id'))
            ->unique()
            ->all();

        return Inertia::render('trainer/programs/edit', [
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
     * Persist edits plus the full workout tree in one submit.
     */
    public function update(Request $request, ExerciseProgram $program): RedirectResponse
    {
        $this->authorizeOwner($request, $program);

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
            ]);

            $this->syncWorkouts($program, $data['workouts'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program updated.'),
        ]);

        return to_route('trainer.programs.edit', $program);
    }

    /**
     * Delete the program (cascades workouts/items; client copies survive
     * as snapshots).
     */
    public function destroy(Request $request, ExerciseProgram $program): RedirectResponse
    {
        $this->authorizeOwner($request, $program);

        $program->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Program deleted.'),
        ]);

        return to_route('trainer.programs.index');
    }

    /**
     * Enable sharing: mints (or reuses) the share token and returns the URL.
     */
    public function share(Request $request, ExerciseProgram $program): JsonResponse
    {
        $this->authorizeOwner($request, $program);

        $program->update([
            'share_token' => $program->share_token ?? (string) Str::uuid(),
        ]);

        return response()->json(['share_url' => $program->shareUrl()]);
    }

    /**
     * Disable sharing: clears the token so the public link stops working.
     */
    public function unshare(Request $request, ExerciseProgram $program): JsonResponse
    {
        $this->authorizeOwner($request, $program);

        $program->update(['share_token' => null]);
        ShortLink::forget($program);

        return response()->json(['share_url' => null]);
    }

    /**
     * Assign the program to linked clients — grants each one access (their
     * own playlist copies) and pings them through the trainer's bot.
     */
    public function assign(Request $request, ExerciseProgram $program): JsonResponse
    {
        $this->authorizeOwner($request, $program);

        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1', 'max:200'],
            'client_ids.*' => ['integer', 'distinct'],
        ]);

        $trainer = $request->user()->ensureTrainer();

        $clients = $trainer->clients()
            ->whereIn('users.id', $data['client_ids'])
            ->get();

        $granter = app(ProgramAccessGranter::class);
        $notifier = app(ClientNotifier::class);

        $granted = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            if ($granter->grant($client, $program)) {
                $granted++;
                $notifier->planAssigned($trainer, $client, $program);
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'granted' => $granted,
            'already_claimed' => $skipped,
        ]);
    }

    /**
     * Only the authoring trainer may touch a program here.
     */
    private function authorizeOwner(Request $request, ExerciseProgram $program): void
    {
        abort_unless($program->user_id === $request->user()->id, 404);
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
     * Validate the program form payload, including the nested workout tree.
     *
     * @return array{name: string, name_am: string|null, description: string|null, description_am: string|null, price_etb: int, highlights?: array<int, string>, highlights_am?: array<int, string>, workouts?: array<int, array{id?: int, title: string, exercises?: array<int, array{slug: string, sets?: int|null, reps?: int|null}>}>}
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
