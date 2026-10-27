<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    /**
     * Searchable, paginated list of translatable strings, filterable by group.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $group = trim((string) $request->query('group', ''));

        return Inertia::render('admin/translations/index', [
            'lines' => LanguageLine::query()
                ->when($search !== '', fn ($q) => $q->where(
                    fn ($q) => $q
                        ->where('key', 'like', "%{$search}%")
                        ->orWhere('text', 'like', "%{$search}%")
                ))
                ->when($group !== '', fn ($q) => $q->where('group', $group))
                ->orderBy('group')
                ->orderBy('key')
                ->paginate(25)
                ->withQueryString(),
            'groups' => LanguageLine::query()->select('group')->distinct()->orderBy('group')->pluck('group'),
            'languages' => Language::query()->where('is_active', true)->orderBy('position')->get(['code', 'name', 'native_name']),
            'filters' => ['search' => $search, 'group' => $group],
        ]);
    }

    /**
     * Edit one string's text per active language.
     */
    public function edit(LanguageLine $translation): Response
    {
        return Inertia::render('admin/translations/edit', [
            'line' => $translation,
            'languages' => Language::query()->where('is_active', true)->orderBy('position')->get(['code', 'name', 'native_name']),
        ]);
    }

    /**
     * Save the text for each language; empty values remove the locale entry
     * so the fallback chain applies.
     */
    public function update(Request $request, LanguageLine $translation): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'array'],
            'text.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $text = [];

        foreach ($data['text'] as $code => $value) {
            if (is_string($value) && trim($value) !== '') {
                $text[$code] = trim($value);
            }
        }

        if ($text === []) {
            return back()->withErrors(['text' => 'At least one language needs a value.']);
        }

        $translation->update(['text' => $text]);

        Translator::flush();

        return back();
    }
}
