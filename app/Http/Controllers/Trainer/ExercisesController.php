<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgramWorkoutItem;
use App\Support\ExerciseCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's private exercise library: custom exercises they author and
 * reuse inside their own programs. They live in the shared `exercises`
 * table behind `user_id`, keyed by a minted `custom-*` slug — names may
 * freely duplicate the platform catalog or each other.
 */
class ExercisesController extends Controller
{
    /**
     * List the trainer's own exercises with search.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('trainer/exercises/index', [
            'exercises' => $request->user()->customExercises()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Exercise $exercise): array => [
                    'id' => $exercise->id,
                    'slug' => $exercise->slug,
                    'name' => $exercise->name,
                    'name_am' => $exercise->name_am,
                    'body_part' => $exercise->body_part,
                    'equipment' => $exercise->equipment,
                    'level' => $exercise->level,
                    'image_url' => $exercise->image_url,
                    'has_video' => $exercise->gif_path !== '',
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Blank custom-exercise form.
     */
    public function create(): Response
    {
        return Inertia::render('trainer/exercises/edit', [
            'exercise' => null,
            'bodyParts' => ExerciseCatalog::BODY_PARTS,
            'levels' => ['beginner', 'intermediate', 'advanced'],
        ]);
    }

    /**
     * Create a custom exercise — slug is minted server-side so names can
     * repeat freely.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $exercise = $request->user()->customExercises()->create([
            'slug' => $this->mintSlug($data['name']),
            'name' => $data['name'],
            'name_am' => $data['name_am'] ?? null,
            'body_part' => $data['body_part'],
            'equipment' => $data['equipment'],
            'target' => $data['target'],
            'level' => $data['level'],
            'instructions' => $data['instructions'] ?? '',
            'instruction_steps' => $this->steps($data['instructions'] ?? ''),
            'image_path' => $this->storeMedia($request, 'image', 'exercises/images'),
            'gif_path' => $this->storeMedia($request, 'video', 'exercises/videos'),
            ...self::DATASET_DEFAULTS,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Exercise created.'),
        ]);

        return to_route('trainer.exercises.index');
    }

    /**
     * Edit form for one custom exercise.
     */
    public function edit(Request $request, Exercise $exercise): Response
    {
        $this->authorizeOwner($request, $exercise);

        return Inertia::render('trainer/exercises/edit', [
            'exercise' => [
                'id' => $exercise->id,
                'slug' => $exercise->slug,
                'name' => $exercise->name,
                'name_am' => $exercise->name_am,
                'body_part' => $exercise->body_part,
                'equipment' => $exercise->equipment,
                'target' => $exercise->target,
                'level' => $exercise->level,
                'instructions' => $exercise->instructions,
                'image_url' => $exercise->image_url,
                'gif_url' => $exercise->gif_path !== '' ? $exercise->gif_url : null,
                'is_video' => $exercise->isVideoDemo(),
            ],
            'bodyParts' => ExerciseCatalog::BODY_PARTS,
            'levels' => ['beginner', 'intermediate', 'advanced'],
        ]);
    }

    /**
     * Persist edits; media uploads replace the old files.
     */
    public function update(Request $request, Exercise $exercise): RedirectResponse
    {
        $this->authorizeOwner($request, $exercise);

        $data = $this->validated($request);

        $exercise->update([
            'name' => $data['name'],
            'name_am' => $data['name_am'] ?? null,
            'body_part' => $data['body_part'],
            'equipment' => $data['equipment'],
            'target' => $data['target'],
            'level' => $data['level'],
            'instructions' => $data['instructions'] ?? '',
            'instruction_steps' => $this->steps($data['instructions'] ?? ''),
        ]);

        $this->replaceMedia($request, $exercise, 'image', 'image_path', 'exercises/images');
        $this->replaceMedia($request, $exercise, 'video', 'gif_path', 'exercises/videos');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Exercise updated.'),
        ]);

        return to_route('trainer.exercises.index');
    }

    /**
     * Delete a custom exercise — blocked while programs still reference
     * its slug, so client copies never lose the entry silently.
     */
    public function destroy(Request $request, Exercise $exercise): RedirectResponse
    {
        $this->authorizeOwner($request, $exercise);

        if (ExerciseProgramWorkoutItem::query()->where('exercise_id', $exercise->slug)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This exercise is used in a program — remove it there first.'),
            ]);

            return back();
        }

        Storage::disk('public')->delete([$exercise->image_path, $exercise->gif_path]);
        $exercise->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Exercise deleted.'),
        ]);

        return to_route('trainer.exercises.index');
    }

    /**
     * Dataset columns a custom row fills with inert defaults — programs
     * and players only read the trainer-facing subset.
     */
    private const DATASET_DEFAULTS = [
        'source_id' => 'custom',
        'category' => 'custom',
        'muscle_group' => 'custom',
        'secondary_muscles' => [],
        'media_id' => '',
        'exercise_type' => 'custom',
        'location' => 'any',
        'movement_pattern' => null,
        'compound' => false,
        'isolation' => false,
        'full_body' => false,
        'women_home_friendly' => true,
        'goal_fat_loss' => false,
        'goal_muscle_gain' => false,
        'review_needed' => false,
        'review_reasons' => [],
    ];

    /**
     * A unique `custom-*` slug — retries until the (globally unique) slug
     * column accepts it.
     */
    private function mintSlug(string $name): string
    {
        $base = 'custom-'.Str::slug($name);

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Exercise::query()->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Split the instructions textarea into steps (one per line).
     *
     * @return array<int, string>
     */
    private function steps(string $instructions): array
    {
        return collect(preg_split('/\r?\n/', $instructions) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Store an uploaded file on the public disk, or '' when absent.
     */
    private function storeMedia(Request $request, string $field, string $directory): string
    {
        return $request->hasFile($field)
            ? ($request->file($field)->store($directory, 'public') ?: '')
            : '';
    }

    /**
     * Replace one media column when a new file was uploaded.
     */
    private function replaceMedia(Request $request, Exercise $exercise, string $field, string $column, string $directory): void
    {
        if (! $request->hasFile($field)) {
            return;
        }

        $old = $exercise->{$column};

        $exercise->update([
            $column => $request->file($field)->store($directory, 'public') ?: $old,
        ]);

        if ($old !== '') {
            Storage::disk('public')->delete($old);
        }
    }

    /**
     * Only the owning trainer may touch a custom exercise.
     */
    private function authorizeOwner(Request $request, Exercise $exercise): void
    {
        abort_unless($exercise->isCustom() && $exercise->user_id === $request->user()->id, 404);
    }

    /**
     * @return array{name: string, name_am?: string|null, body_part: string, equipment: string, target: string, level: string, instructions?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'body_part' => ['required', Rule::in(ExerciseCatalog::BODY_PARTS)],
            'equipment' => ['required', 'string', 'max:60'],
            'target' => ['required', 'string', 'max:80'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov,gif', 'max:20480'],
        ]);
    }
}
