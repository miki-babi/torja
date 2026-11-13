<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\ProgramLead;
use App\Models\Trainer;
use App\Models\TrainerLeadField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's claim-form builder: whether guests must answer before
 * taking a free program, and which questions the form asks. An empty
 * field list falls back to the preset questionnaire.
 */
class LeadFormController extends Controller
{
    /**
     * Show the builder with the current mode and fields.
     */
    public function edit(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/lead-form', [
            'mode' => $trainer->lead_form_mode,
            'usingDefaults' => $trainer->leadFields()->doesntExist(),
            'defaultFields' => collect(ProgramLead::presetFields())
                ->map(fn (array $field): array => [
                    'label' => $field['label'],
                    'type' => 'select',
                    'options' => array_column($field['options'], 'label'),
                    'required' => $field['required'],
                ])
                ->all(),
            'fields' => $trainer->leadFields()->get()
                ->map(fn (TrainerLeadField $field): array => [
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'options' => $field->options ?? [],
                    'required' => $field->required,
                ])
                ->all(),
        ]);
    }

    /**
     * Save the mode and replace the field list — submitted order becomes
     * display order.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(Trainer::LEAD_FORM_MODES)],
            'fields' => ['nullable', 'array', 'max:10'],
            'fields.*.label' => ['required', 'string', 'max:200'],
            'fields.*.type' => ['required', Rule::in(TrainerLeadField::TYPES)],
            'fields.*.options' => ['nullable', 'array', 'max:10'],
            'fields.*.options.*' => ['required', 'string', 'max:120'],
            'fields.*.required' => ['boolean'],
        ]);

        $trainer = $request->user()->ensureTrainer();
        $trainer->update(['lead_form_mode' => $data['mode']]);

        $fields = collect($data['fields'] ?? [])
            ->map(fn (array $field, int $index): array => [
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['type'] === 'select'
                    ? array_values($field['options'] ?? [])
                    : null,
                'required' => $field['required'] ?? true,
                'position' => $index,
            ]);

        $trainer->leadFields()->delete();
        $trainer->leadFields()->createMany($fields->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Claim form saved.'),
        ]);

        return back();
    }
}
