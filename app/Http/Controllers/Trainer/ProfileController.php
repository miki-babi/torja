<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's public-facing profile and payout details — shown on their
 * shareable /t/{slug} page and used when the platform pays out earnings.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/profile', [
            'trainer' => [
                'display_name' => $trainer->display_name,
                'bio' => $trainer->bio,
                'specialty' => $trainer->specialty,
                'languages' => $trainer->languages ?? [],
                'years_experience' => $trainer->years_experience,
                'has_certification' => $trainer->certification_path !== null,
                'payout_method' => $trainer->payout_method,
                'payout_account' => $trainer->payout_account,
                'offer_text' => $trainer->offer_text,
                'page_url' => $trainer->inviteUrl(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $trainer = $request->user()->ensureTrainer();

        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:30'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'payout_method' => ['nullable', 'string', 'in:telebirr,bank'],
            'payout_account' => ['nullable', 'string', 'max:120'],
            'offer_text' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->hasFile('certification')) {
            $request->validate([
                'certification' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);

            $path = $request->file('certification')
                ->store("certifications/{$trainer->id}", 'public');

            if ($path !== false) {
                if ($trainer->certification_path !== null) {
                    Storage::disk('public')->delete($trainer->certification_path);
                }

                $data['certification_path'] = $path;
            }
        }

        $trainer->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Profile saved.'),
        ]);

        return back();
    }
}
