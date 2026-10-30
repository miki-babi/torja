<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ProPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProPageController extends Controller
{
    /**
     * The Pro page editor: current settings (defaults overlaid with saved
     * overrides), the shipped defaults for the reset preview, and the icon
     * whitelist for feature rows and trust badges.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/pro-page/edit', [
            'settings' => ProPage::settings(),
            'defaults' => ProPage::defaults(),
            'envPrice' => (int) config('plans.pro_price_etb'),
            'icons' => ProPage::ICONS,
        ]);
    }

    /**
     * Save the whole Pro page payload as one setting.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(ProPage::rules());

        Setting::set(ProPage::SETTING_KEY, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro page saved.'),
        ]);

        return back();
    }

    /**
     * Drop the saved overrides so the page falls back to the shipped copy.
     */
    public function destroy(): RedirectResponse
    {
        Setting::forget(ProPage::SETTING_KEY);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro page reset to defaults.'),
        ]);

        return back();
    }
}
