<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff management of the shared exercise library — browse the seeded
 * catalog and fix names/translations/categorisation. Media stays read-only;
 * new exercises come from the seeder pipeline.
 */
class ExercisesController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $category = $request->string('category')->toString();

        $exercises = Exercise::query()
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%")))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/exercises/index', [
            'exercises' => $exercises->through(fn (Exercise $exercise): array => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'name_am' => $exercise->name_am,
                'category' => $exercise->category,
                'equipment' => $exercise->equipment,
                'target' => $exercise->target,
                'level' => $exercise->level,
                'gif_url' => $exercise->gif_url,
                'review_needed' => $exercise->review_needed,
            ]),
            'categories' => Exercise::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => ['search' => $search, 'category' => $category],
        ]);
    }

    public function edit(Exercise $exercise): Response
    {
        return Inertia::render('admin/exercises/edit', [
            'exercise' => $exercise->only([
                'id', 'name', 'name_am', 'category', 'equipment', 'body_part',
                'target', 'level', 'instructions', 'instructions_am', 'translation_status',
            ]) + ['gif_url' => $exercise->gif_url],
        ]);
    }

    public function update(Request $request, Exercise $exercise): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'name_am' => ['nullable', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:60'],
            'equipment' => ['required', 'string', 'max:60'],
            'body_part' => ['required', 'string', 'max:60'],
            'target' => ['required', 'string', 'max:60'],
            'level' => ['required', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'instructions_am' => ['nullable', 'string', 'max:2000'],
        ]);

        $exercise->update($validated + [
            'translation_status' => isset($validated['name_am']) ? 'translated' : $exercise->translation_status,
            'review_needed' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exercise updated.')]);

        return to_route('admin.exercises.index');
    }
}
