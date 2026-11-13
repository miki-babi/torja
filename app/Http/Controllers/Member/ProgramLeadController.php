<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public lead-capture funnel behind a free trainer program: a guest
 * answers a short questionnaire instead of anonymously grabbing the
 * program, then lands on a thank-you page that nudges registration. The
 * lead is claimed onto their account when they sign up (see the
 * `program_lead` session flag consumed by RedirectsUsersByRole).
 */
class ProgramLeadController extends Controller
{
    /**
     * The questionnaire shown before a guest may take a free program.
     * Paid or platform programs skip it entirely.
     */
    public function create(ExerciseProgram $program): Response|RedirectResponse
    {
        $trainer = $this->leadableTrainer($program);

        if ($trainer === null || $trainer->lead_form_mode === 'off') {
            return to_route('programs.shared.show', $program->share_token);
        }

        return Inertia::render('public/program-lead', [
            'program' => [
                'name' => $program->localizedName(),
                'share_token' => $program->share_token,
            ],
            'trainer' => [
                'name' => $trainer->display_name,
            ],
            'fields' => $trainer->leadFormFields(),
            'optional' => $trainer->lead_form_mode === 'optional',
            'skip_url' => $trainer->lead_form_mode === 'optional'
                ? route('programs.shared.lead.skip', $program->share_token)
                : null,
            'program_url' => route('programs.shared.show', $program->share_token),
        ]);
    }

    /**
     * Store the lead, seed the session so post-auth links them to the
     * coach and claims the lead, then show the thank-you screen.
     */
    public function store(Request $request, ExerciseProgram $program): RedirectResponse
    {
        $trainer = $this->leadableTrainer($program);
        abort_if($trainer === null || $trainer->lead_form_mode === 'off', 404);

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'answers' => ['required', 'array'],
        ];

        foreach ($trainer->leadFormFields() as $field) {
            $rules["answers.{$field['key']}"] = [
                $field['required'] ? 'required' : 'nullable',
                'string',
                'max:500',
                ...($field['type'] === 'select'
                    ? [Rule::in(array_column($field['options'], 'value'))]
                    : []),
            ];
        }

        $data = $request->validate($rules);

        $lead = $trainer->leads()->create([
            'exercise_program_id' => $program->id,
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'answers' => $data['answers'],
            'source' => 'public_page',
        ]);

        // Registration/login consumes both flags: `tenant` links the new
        // account to this coach, `program_lead` stamps this lead's user_id.
        $request->session()->put('tenant', $trainer->slug);
        $request->session()->put('program_lead', $lead->id);

        return to_route('programs.shared.lead.thanks', $program->share_token);
    }

    /**
     * Optional-form escape: the guest continues without leaving answers.
     * The tenant flag still seeds so their sign-up links to the coach.
     */
    public function skip(Request $request, ExerciseProgram $program): RedirectResponse
    {
        $trainer = $this->leadableTrainer($program);
        abort_if($trainer === null || $trainer->lead_form_mode !== 'optional', 404);

        $request->session()->put('tenant', $trainer->slug);

        return to_route('programs.shared.show', $program->share_token);
    }

    /**
     * The after-submit screen: thanks plus a register CTA. Only reachable
     * with a pending lead so it can't be indexed or spoofed usefully.
     */
    public function thanks(Request $request, ExerciseProgram $program): Response|RedirectResponse
    {
        $trainer = $program->user->trainer;

        if ($trainer === null) {
            return to_route('programs.shared.show', $program->share_token);
        }

        return Inertia::render('public/program-lead-thanks', [
            'trainer' => [
                'name' => $trainer->display_name,
                'slug' => $trainer->slug,
            ],
            'program' => [
                'name' => $program->localizedName(),
            ],
            'register_url' => route('register'),
            'login_url' => route('login'),
        ]);
    }

    /**
     * The tenant behind a free, trainer-authored program — the only shape
     * that runs the lead funnel.
     */
    private function leadableTrainer(ExerciseProgram $program): ?Trainer
    {
        if (! $program->isFree() || ! $program->user->isTrainer()) {
            return null;
        }

        return $program->user->trainer;
    }
}
