<?php

namespace App\Http\Controllers\Plan;

use App\Enums\WizardSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\WizardStepRequest;
use App\Models\Food;
use App\Models\Ingredient;
use App\Services\Nutrition\ProfileResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class NutritionWizardController extends Controller
{
    /**
     * Food category → picker section. Categories not listed are excluded
     * from the picker (beverages, condiments, fats, sugar, misc, plan-only).
     *
     * @var array<string, string>
     */
    private const CATEGORY_SECTIONS = [
        'Mixed' => 'traditional_ethiopian',
        'Soups and sauces' => 'traditional_ethiopian',
        'Meat, poultry, and their products' => 'protein_rich',
        'Eggs and their products' => 'protein_rich',
        'Fish, seafood, and their products' => 'protein_rich',
        'Milk and its products' => 'protein_rich',
        'Legumes and their products' => 'protein_rich',
        'Nuts, seeds, and their products' => 'protein_rich',
        'Cereals and their products' => 'other_staples',
        'Starchy roots, tubers, and their products' => 'other_staples',
        'Fruits and their products' => 'fruit_and_veg',
        'Vegetables and their products' => 'fruit_and_veg',
    ];

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
            'picker' => Inertia::defer(fn (): array => $this->pickerProps()),
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

    /**
     * Picker data loaded as a deferred prop so the first paint stays light.
     *
     * @return array{foodGroups: array<int, array{section: string, categories: array<int, array{name: string, foods: array<int, array{id: int, name_en: string, name_am: string|null, allergens: array<int, string>, meal_types: array<int, string>}>}>}>, ingredients: array<int, array{id: int, name_en: string, name_am: string|null, allergens: array<int, string>, common_allergy_label: string|null}>}
     */
    private function pickerProps(): array
    {
        $foods = Food::query()
            ->where('meal_types', '!=', '[]')
            ->whereIn('category', array_keys(self::CATEGORY_SECTIONS))
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_am', 'category', 'allergens', 'meal_types']);

        $sections = ['traditional_ethiopian', 'protein_rich', 'other_staples', 'fruit_and_veg'];

        $foodGroups = collect($sections)->map(function (string $section) use ($foods): array {
            $categories = $foods
                ->filter(fn (Food $food): bool => self::CATEGORY_SECTIONS[$food->category] === $section)
                ->groupBy('category')
                ->map(fn (Collection $group, string $category): array => [
                    'name' => $category,
                    'foods' => $group->map(fn (Food $food): array => [
                        'id' => $food->id,
                        'name_en' => $food->name_en,
                        'name_am' => $food->name_am,
                        'allergens' => $food->allergens ?? [],
                        'meal_types' => $food->meal_types ?? [],
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            return ['section' => $section, 'categories' => $categories];
        })->all();

        $ingredients = Ingredient::query()
            ->where('show_in_onboarding', true)
            ->orWhere('allergens', '!=', '[]')
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_am', 'allergens', 'common_allergy_label'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name_en' => $ingredient->name_en,
                'name_am' => $ingredient->name_am,
                'allergens' => $ingredient->allergens ?? [],
                'common_allergy_label' => $ingredient->common_allergy_label,
            ])
            ->all();

        return ['foodGroups' => $foodGroups, 'ingredients' => $ingredients];
    }
}
