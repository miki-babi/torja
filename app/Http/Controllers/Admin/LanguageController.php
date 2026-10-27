<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    /**
     * List all languages, active first by position.
     */
    public function index(): Response
    {
        return Inertia::render('admin/languages/index', [
            'languages' => Language::query()
                ->orderBy('position')
                ->orderBy('code')
                ->get(),
        ]);
    }

    /**
     * Create a new language.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_dash', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
        ]);

        Language::create([
            ...$data,
            'is_active' => true,
            'is_default' => false,
            'position' => ((int) Language::max('position')) + 1,
        ]);

        Locales::flush();

        return back();
    }

    /**
     * Update name, flag, and ordering for one language.
     */
    public function update(Request $request, Language $language): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($data['is_default']) {
            $data['is_active'] = true;
        }

        if ($language->is_default && (! $data['is_default'] || ! $data['is_active'])) {
            abort(422, 'The default language must stay active and default.');
        }

        $language->update($data);

        if ($data['is_default']) {
            Language::query()
                ->whereKeyNot($language->id)
                ->update(['is_default' => false]);
        }

        Locales::flush();

        return back();
    }
}
