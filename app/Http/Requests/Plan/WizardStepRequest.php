<?php

namespace App\Http\Requests\Plan;

use App\Enums\WizardSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one wizard section PATCH. Rules switch on the {section} route
 * parameter, which is constrained to WizardSection values by the route
 * definition.
 */
class WizardStepRequest extends FormRequest
{
    /**
     * The EU-14 allergen codes used by the food dataset.
     *
     * @var array<int, string>
     */
    private const ALLERGEN_CODES = [
        'gluten_cereals',
        'crustaceans',
        'eggs',
        'fish',
        'peanuts',
        'soy',
        'milk_dairy',
        'tree_nuts',
        'celery',
        'mustard',
        'sesame',
        'sulphites',
        'lupin',
        'molluscs',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $section = WizardSection::tryFrom((string) $this->route('section'));

        return match ($section) {
            WizardSection::Goal => [
                'primary_goal' => ['required', 'string', Rule::in(['lose_fat', 'build_muscle', 'recomp', 'maintain', 'general'])],
                'motivations' => ['required', 'array', 'min:1'],
                'motivations.*' => ['string', Rule::in(['appearance', 'strength', 'health', 'energy', 'sports_performance', 'other'])],
                'motivation_other' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::requiredIf(fn (): bool => in_array('other', $this->input('motivations', []), true)),
                    Rule::prohibitedIf(fn (): bool => ! in_array('other', $this->input('motivations', []), true)),
                ],
                'target_weight_kg' => ['nullable', 'numeric', 'gt:0', 'lt:500'],
                'priorities' => ['required', 'array', 'max:2'],
                'priorities.*' => ['string', Rule::in(['affordability', 'convenience', 'traditional_ethiopian', 'variety', 'high_protein'])],
            ],
            WizardSection::Body => [
                'age' => ['required', 'integer', 'min:10', 'max:100'],
                'height_cm' => ['required', 'numeric', 'min:50', 'max:300'],
                'weight_kg' => ['required', 'numeric', 'min:20', 'max:500'],
                'sex' => ['required', 'string', Rule::in(['male', 'female', 'not_say'])],
                'waist_cm' => ['nullable', 'numeric', 'min:30', 'max:300'],
                'body_fat_percent' => ['nullable', 'numeric', 'min:2', 'max:70'],
                'body_fat_is_estimate' => ['sometimes', 'boolean'],
                'weight_trend' => ['required', 'string', Rule::in(['stable', 'increasing', 'decreasing', 'changing', 'unsure'])],
                'medical_flags' => ['present', 'array'],
                'medical_flags.*' => ['string', Rule::in(['pregnancy', 'breastfeeding', 'medical_diet_supervision', 'none'])],
            ],
            WizardSection::Activity => [
                'daily_activity' => ['required', 'string', Rule::in(['sedentary', 'light', 'moderate', 'heavy'])],
                'exercise_days' => ['required', 'string', Rule::in(['none', '1_2', '3_5', '6_7'])],
                'exercise_types' => ['present', 'array'],
                'exercise_types.*' => ['string', 'max:50'],
                'avg_daily_steps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            ],
            WizardSection::Pace => [
                'pace' => ['required', 'string', Rule::in(['gradual', 'steady', 'recommend'])],
                'target_date' => ['nullable', 'date'],
            ],
            WizardSection::Diet => [
                'diet_type' => ['required', 'string', Rule::in(['omnivore', 'vegetarian', 'vegan', 'certain_days', 'other'])],
                'diet_other' => ['nullable', 'string', 'max:255', 'required_if:diet_type,other', 'prohibited_unless:diet_type,other'],
                'fasting' => ['required', 'string', Rule::in(['none', 'wed_fri', 'seasonal', 'different', 'custom'])],
                'fasting_note' => ['nullable', 'string', 'max:500', 'required_if:fasting,custom', 'prohibited_if:fasting,none'],
                'adapt_to_fasting' => ['required', 'boolean'],
                'other_rules' => ['nullable', 'string', 'max:1000'],
            ],
            WizardSection::LikedFoods => [
                'liked_foods' => ['present', 'array'],
                'liked_foods.*.food_id' => ['required', 'integer', 'distinct', Rule::exists('foods', 'id')],
                'liked_foods.*.tags' => ['required', 'array', 'min:1'],
                'liked_foods.*.tags.*' => ['string', Rule::in(['eat_regularly', 'enjoy', 'want_more'])],
            ],
            WizardSection::Avoidances => [
                'disliked_food_ids' => ['present', 'array'],
                'disliked_food_ids.*' => ['integer', 'distinct', Rule::exists('foods', 'id')],
                'allergen_codes' => ['present', 'array'],
                'allergen_codes.*' => ['string', Rule::in(self::ALLERGEN_CODES)],
                'excluded_ingredient_ids' => ['present', 'array'],
                'excluded_ingredient_ids.*' => ['integer', 'distinct', Rule::exists('ingredients', 'id')],
                'allergy_other' => ['nullable', 'string', 'max:500'],
                'discomfort_ingredient_ids' => ['present', 'array'],
                'discomfort_ingredient_ids.*' => ['integer', 'distinct', Rule::exists('ingredients', 'id')],
                'discomfort_note' => ['nullable', 'string', 'max:500'],
            ],
            WizardSection::Habits => [
                'usual_meals' => ['present', 'array'],
                'usual_meals.*' => ['string', Rule::in(['breakfast', 'lunch', 'dinner', 'snacks', 'late_night'])],
                'diary' => ['present', 'array', 'max:8'],
                'diary.*.meal' => ['required', 'string', Rule::in(['breakfast', 'lunch', 'dinner', 'snacks', 'late_night'])],
                'diary.*.food_id' => ['nullable', 'integer', Rule::exists('foods', 'id')],
                'diary.*.free_text' => ['nullable', 'string', 'max:255'],
                'diary.*.portion_note' => ['nullable', 'string', 'max:255'],
                'improvements' => ['present', 'array'],
                'improvements.*' => ['string', Rule::in(['more_vegetables', 'more_protein', 'less_sugar', 'less_fried', 'regular_meal_times', 'smaller_portions', 'more_water'])],
            ],
            WizardSection::Schedule => [
                'meals_per_day' => ['required', 'string', Rule::in(['2', '3', '3_snacks', '4_5', 'recommend'])],
                'wake_time' => ['nullable', 'date_format:H:i'],
                'sleep_time' => ['nullable', 'date_format:H:i'],
                'weekday_schedule' => ['required', 'string', Rule::in(['regular', 'shifts', 'variable', 'nights', 'other'])],
                'eating_location' => ['required', 'string', Rule::in(['home', 'work_school', 'restaurants', 'mixed'])],
                'portable_meals' => ['required', 'string', Rule::in(['yes', 'no', 'sometimes'])],
            ],
            WizardSection::Budget => [
                'budget_type' => ['required', 'string', Rule::in(['daily', 'weekly', 'none'])],
                'budget_amount_etb' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
                'food_sources' => ['required', 'array'],
                'food_sources.*' => ['string', Rule::in(['market', 'supermarket', 'restaurants', 'mixed'])],
                'ingredient_availability' => ['required', 'string', Rule::in(['easy', 'usually', 'sometimes', 'hard'])],
                'allow_substitutions' => ['required', 'boolean'],
            ],
            WizardSection::Cooking => [
                'cook' => ['required', 'string', Rule::in(['self', 'family', 'prepared', 'shared'])],
                'cook_time' => ['prohibited_if:cook,prepared', 'required_unless:cook,prepared', 'string', Rule::in(['lt15', '15_30', '30_60', 'gt60'])],
                'kitchen' => ['prohibited_if:cook,prepared', 'required_unless:cook,prepared', 'array'],
                'kitchen.*' => ['string', Rule::in(['basic', 'fridge', 'freezer', 'stove', 'oven', 'blender', 'none'])],
                'repetition' => ['required', 'string', Rule::in(['fine', 'lunch_dinner_reuse', 'varied', 'batch'])],
            ],
            WizardSection::Obstacles => [
                'obstacles' => ['required', 'array', 'min:1'],
                'obstacles.*' => ['string', Rule::in(['no_time', 'motivation', 'cost', 'family_meals', 'eating_out', 'stress_eating', 'cravings', 'cooking_skills', 'irregular_schedule', 'other'])],
                'obstacle_other' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::requiredIf(fn (): bool => in_array('other', $this->input('obstacles', []), true)),
                    Rule::prohibitedIf(fn (): bool => ! in_array('other', $this->input('obstacles', []), true)),
                ],
                'support' => ['present', 'array'],
                'support.*' => ['string', Rule::in(['meal_reminders', 'grocery_list', 'simple_recipes', 'family_involvement', 'coach_checkins', 'progress_tracking', 'fasting_adaptations', 'eating_out_guidance'])],
            ],
            default => [],
        };
    }
}
