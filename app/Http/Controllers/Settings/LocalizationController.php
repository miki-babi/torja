<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocalizationController extends Controller
{
    /**
     * Persist the member's UI language (user column) and mirror it in the
     * locale cookie. Guests hit the same endpoint via the public locale
     * route — only the cookie applies to them.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::codes())],
        ]);

        $request->user()?->update(['locale' => $data['locale']]);

        return back()->withCookie(
            Cookie::make('locale', $data['locale'], 60 * 24 * 365),
        );
    }
}
