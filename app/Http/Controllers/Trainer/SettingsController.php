<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trainer settings: interface language (handled by the shared locale
 * endpoint) plus their bot's client-reminder behaviour — on/off and the
 * quiet-hours window during which no DMs go out.
 */
class SettingsController extends Controller
{
    /**
     * Show the settings screen.
     */
    public function edit(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/settings', [
            'locale' => $request->user()->locale ?? 'en',
            'reminders' => [
                'enabled' => $trainer->reminders_enabled,
                'quiet_hours_start' => $trainer->quiet_hours_start === null
                    ? null
                    : substr((string) $trainer->quiet_hours_start, 0, 5),
                'quiet_hours_end' => $trainer->quiet_hours_end === null
                    ? null
                    : substr((string) $trainer->quiet_hours_end, 0, 5),
            ],
        ]);
    }

    /**
     * Save the reminder preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reminders_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['nullable', 'required_with:quiet_hours_end', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'required_with:quiet_hours_start', 'date_format:H:i'],
        ]);

        $request->user()->ensureTrainer()->update([
            'reminders_enabled' => $data['reminders_enabled'],
            'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Settings saved.'),
        ]);
    }
}
