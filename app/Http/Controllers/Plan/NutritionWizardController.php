<?php

namespace App\Http\Controllers\Plan;

use App\Enums\WizardSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\WizardStepRequest;
use App\Services\Nutrition\ProfileResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionWizardController extends Controller
{
    public function __construct(private readonly ProfileResolver $profiles) {}

    /**
     * Show the wizard, resuming at the profile's saved step.
     */
    public function edit(Request $request): Response
    {
        $profile = $this->profiles->for($request);

        return Inertia::render('plan/wizard', [
            'currentStep' => $profile->current_step,
            'status' => $profile->status,
            'answers' => $profile->answers(),
        ]);
    }

    /**
     * Persist one wizard section and advance the profile's saved step.
     */
    public function update(WizardStepRequest $request, string $section): RedirectResponse
    {
        $profile = $this->profiles->for($request);

        $wizardSection = WizardSection::from($section);

        $profile->{$wizardSection->value} = $request->validated();

        if ($wizardSection === WizardSection::Obstacles) {
            $profile->status = 'completed';
            $profile->completed_at = now();
        } else {
            $profile->current_step = max($profile->current_step, $wizardSection->step() + 1);
        }

        $profile->save();

        $tracker = app(Tracker::class);

        $stepProperties = [
            'section' => $wizardSection->value,
            'step' => $wizardSection->step(),
        ];

        if ($wizardSection === WizardSection::Goal) {
            $stepProperties['primary_goal'] = $profile->goal['primary_goal'] ?? null;
        }

        $tracker->record('wizard.step_completed', properties: $stepProperties);

        if ($wizardSection === WizardSection::Obstacles) {
            $tracker->record('wizard.completed', properties: [
                'primary_goal' => $profile->goal['primary_goal'] ?? null,
                'motivations' => $profile->goal['motivations'] ?? [],
            ]);
        }

        return back();
    }

    /**
     * Show a summary of all saved answers before the preview paywall.
     */
    public function review(Request $request): Response|RedirectResponse
    {
        $profile = $this->profiles->for($request);

        if (! $profile->hasAnswers()) {
            return to_route('plan.wizard');
        }

        return Inertia::render('plan/review', [
            'status' => $profile->status,
            'answers' => $profile->answers(),
        ]);
    }
}
