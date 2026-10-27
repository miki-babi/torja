import type { FormDataConvertible } from '@inertiajs/core';

/**
 * Option lists, labels and section metadata shared by the nutrition-plan
 * wizard steps and the review page. Values must stay in sync with the
 * validation contract in App\Http\Requests\Plan\WizardStepRequest.
 */

export type Option = {
    value: string;
    label: string;
    description?: string;
};

export type LikedFood = {
    food_id: number;
    tags: string[];
};

export type DiaryEntry = {
    meal: string;
    food_id?: number | null;
    /** Client-only display label for the picked food; stripped before PATCH. */
    _food_label?: string;
    free_text?: string;
    portion_note?: string;
};

export interface GoalAnswers {
    primary_goal: string;
    motivations: string[];
    motivation_other: string;
    target_weight_kg: string | number;
    priorities: string[];
}

export interface BodyAnswers {
    age: string | number;
    height_cm: string | number;
    weight_kg: string | number;
    sex: string;
    waist_cm: string | number;
    body_fat_percent: string | number;
    body_fat_is_estimate: boolean;
    weight_trend: string;
    medical_flags: string[];
}

export interface ActivityAnswers {
    daily_activity: string;
    exercise_days: string;
    exercise_types: string[];
    avg_daily_steps: string | number;
}

export interface PaceAnswers {
    pace: string;
    target_date: string;
}

export interface DietAnswers {
    diet_type: string;
    diet_other: string;
    fasting: string;
    fasting_note: string;
    adapt_to_fasting: boolean;
    other_rules: string;
}

export interface LikedFoodsAnswers {
    liked_foods: LikedFood[];
}

export interface AvoidancesAnswers {
    disliked_food_ids: number[];
    allergen_codes: string[];
    excluded_ingredient_ids: number[];
    allergy_other: string;
    discomfort_ingredient_ids: number[];
    discomfort_note: string;
}

export interface HabitsAnswers {
    usual_meals: string[];
    diary: DiaryEntry[];
    improvements: string[];
}

export interface ScheduleAnswers {
    meals_per_day: string;
    wake_time: string;
    sleep_time: string;
    weekday_schedule: string;
    eating_location: string;
    portable_meals: string;
}

export interface BudgetAnswers {
    budget_type: string;
    budget_amount_etb: string | number;
    food_sources: string[];
    ingredient_availability: string;
    allow_substitutions: boolean;
}

export interface CookingAnswers {
    cook: string;
    cook_time: string;
    kitchen: string[];
    repetition: string;
}

export interface ObstaclesAnswers {
    obstacles: string[];
    obstacle_other: string;
    support: string[];
}

export interface WizardAnswers {
    goal: GoalAnswers;
    body: BodyAnswers;
    activity: ActivityAnswers;
    pace: PaceAnswers;
    diet: DietAnswers;
    liked_foods: LikedFoodsAnswers;
    avoidances: AvoidancesAnswers;
    habits: HabitsAnswers;
    schedule: ScheduleAnswers;
    budget: BudgetAnswers;
    cooking: CookingAnswers;
    obstacles: ObstaclesAnswers;
}

export type SectionKey = keyof WizardAnswers;

/** Wizard step (1-based) → section key. Step 1 is the intro screen. */
export const STEP_SECTIONS: Record<number, SectionKey> = {
    2: 'goal',
    3: 'body',
    4: 'activity',
    5: 'pace',
    6: 'diet',
    7: 'liked_foods',
    8: 'avoidances',
    9: 'habits',
    10: 'schedule',
    11: 'budget',
    12: 'cooking',
    13: 'obstacles',
};

export const TOTAL_STEPS = 13;

export const SECTION_META: Record<
    SectionKey,
    { step: number; title: string; description: string }
> = {
    goal: {
        step: 2,
        title: 'Your goal',
        description: 'What do you want to achieve?',
    },
    body: {
        step: 3,
        title: 'About your body',
        description: 'Used to estimate your energy needs — estimates only.',
    },
    activity: {
        step: 4,
        title: 'Activity',
        description: 'How active are you day to day?',
    },
    pace: {
        step: 5,
        title: 'Pace',
        description: 'How fast do you want to move?',
    },
    diet: {
        step: 6,
        title: 'How you eat',
        description: 'Dietary pattern and fasting.',
    },
    liked_foods: {
        step: 7,
        title: 'Foods you like',
        description: 'Pick foods you enjoy — your plan will lean on them.',
    },
    avoidances: {
        step: 8,
        title: 'Foods to avoid',
        description: 'Allergies, intolerances and dislikes.',
    },
    habits: {
        step: 9,
        title: 'Current habits',
        description: 'What eating looks like for you today.',
    },
    schedule: {
        step: 10,
        title: 'Your schedule',
        description: 'When and where you eat.',
    },
    budget: {
        step: 11,
        title: 'Budget',
        description: 'What you can spend on food.',
    },
    cooking: {
        step: 12,
        title: 'Cooking',
        description: 'Who cooks and how much time you have.',
    },
    obstacles: {
        step: 13,
        title: 'Obstacles & support',
        description: 'What might get in the way, and what would help.',
    },
};

export const PRIMARY_GOALS: Option[] = [
    { value: 'lose_fat', label: 'Lose fat' },
    { value: 'build_muscle', label: 'Build muscle' },
    { value: 'recomp', label: 'Lose fat & build muscle (recomp)' },
    { value: 'maintain', label: 'Maintain my weight' },
    { value: 'general', label: 'Eat healthier overall' },
];

export const MOTIVATIONS: Option[] = [
    { value: 'appearance', label: 'Look better' },
    { value: 'strength', label: 'Get stronger' },
    { value: 'health', label: 'Improve my health' },
    { value: 'energy', label: 'Have more energy' },
    { value: 'sports_performance', label: 'Perform better in sport' },
    { value: 'other', label: 'Something else' },
];

export const PRIORITIES: Option[] = [
    { value: 'affordability', label: 'Affordable meals' },
    { value: 'convenience', label: 'Quick & convenient' },
    { value: 'traditional_ethiopian', label: 'Traditional Ethiopian food' },
    { value: 'variety', label: 'Lots of variety' },
    { value: 'high_protein', label: 'High protein' },
];

export const SEX_OPTIONS: Option[] = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'not_say', label: 'Prefer not to say' },
];

export const WEIGHT_TRENDS: Option[] = [
    { value: 'stable', label: 'Stable' },
    { value: 'increasing', label: 'Going up' },
    { value: 'decreasing', label: 'Going down' },
    { value: 'changing', label: 'It goes up and down' },
    { value: 'unsure', label: 'Not sure' },
];

export const MEDICAL_FLAGS: Option[] = [
    { value: 'pregnancy', label: 'Pregnancy' },
    { value: 'breastfeeding', label: 'Breastfeeding' },
    {
        value: 'medical_diet_supervision',
        label: 'A condition requiring diet supervision (e.g. diabetes, kidney)',
    },
    { value: 'none', label: 'None of these' },
];

export const DAILY_ACTIVITY: Option[] = [
    {
        value: 'sedentary',
        label: 'Mostly sitting',
        description: 'Desk work, little walking',
    },
    {
        value: 'light',
        label: 'Lightly active',
        description: 'Some walking, light chores',
    },
    {
        value: 'moderate',
        label: 'Moderately active',
        description: 'On my feet much of the day',
    },
    {
        value: 'heavy',
        label: 'Very active',
        description: 'Physical labour or heavy daily activity',
    },
];

export const EXERCISE_DAYS: Option[] = [
    { value: 'none', label: 'None' },
    { value: '1_2', label: '1–2 days / week' },
    { value: '3_5', label: '3–5 days / week' },
    { value: '6_7', label: '6–7 days / week' },
];

export const EXERCISE_TYPES: Option[] = [
    { value: 'walking', label: 'Walking' },
    { value: 'running', label: 'Running' },
    { value: 'gym_weights', label: 'Gym / weights' },
    { value: 'sports', label: 'Sports (football, basketball…)' },
    { value: 'cycling', label: 'Cycling' },
    { value: 'swimming', label: 'Swimming' },
    { value: 'home_workout', label: 'Home workouts' },
    { value: 'other', label: 'Other' },
];

export const PACES: Option[] = [
    {
        value: 'gradual',
        label: 'Gradual',
        description: 'Slower change, easier to sustain',
    },
    {
        value: 'steady',
        label: 'Steady',
        description: 'Faster change with more effort',
    },
    {
        value: 'recommend',
        label: 'Recommend for me',
        description: 'Let the plan pick a safe pace',
    },
];

export const DIET_TYPES: Option[] = [
    { value: 'omnivore', label: 'I eat everything' },
    { value: 'vegetarian', label: 'Vegetarian' },
    { value: 'vegan', label: 'Vegan' },
    {
        value: 'certain_days',
        label: 'Plant-based on certain days (e.g. fasting days)',
    },
    { value: 'other', label: 'Other' },
];

export const FASTING_OPTIONS: Option[] = [
    { value: 'none', label: 'No' },
    { value: 'wed_fri', label: 'Yes — Wednesdays & Fridays' },
    { value: 'seasonal', label: 'Yes — fasting seasons (e.g. Lent)' },
    { value: 'different', label: 'Yes — different days' },
    { value: 'custom', label: 'Yes — a custom pattern' },
];

export const LIKED_FOOD_TAGS: Option[] = [
    { value: 'eat_regularly', label: 'Eat regularly' },
    { value: 'enjoy', label: 'Enjoy' },
    { value: 'want_more', label: 'Want more of this' },
];

/** EU-14 allergen codes used by the food dataset. */
export const ALLERGEN_OPTIONS: Option[] = [
    { value: 'gluten_cereals', label: 'Gluten / cereals' },
    { value: 'crustaceans', label: 'Crustaceans' },
    { value: 'eggs', label: 'Eggs' },
    { value: 'fish', label: 'Fish' },
    { value: 'peanuts', label: 'Peanuts' },
    { value: 'soy', label: 'Soy' },
    { value: 'milk_dairy', label: 'Milk / dairy' },
    { value: 'tree_nuts', label: 'Tree nuts' },
    { value: 'celery', label: 'Celery' },
    { value: 'mustard', label: 'Mustard' },
    { value: 'sesame', label: 'Sesame' },
    { value: 'sulphites', label: 'Sulphites' },
    { value: 'lupin', label: 'Lupin' },
    { value: 'molluscs', label: 'Molluscs' },
];

export const USUAL_MEALS: Option[] = [
    { value: 'breakfast', label: 'Breakfast' },
    { value: 'lunch', label: 'Lunch' },
    { value: 'dinner', label: 'Dinner' },
    { value: 'snacks', label: 'Snacks' },
    { value: 'late_night', label: 'Late-night eating' },
];

export const IMPROVEMENTS: Option[] = [
    { value: 'more_vegetables', label: 'More vegetables' },
    { value: 'more_protein', label: 'More protein' },
    { value: 'less_sugar', label: 'Less sugar / sweets' },
    { value: 'less_fried', label: 'Less fried food' },
    { value: 'regular_meal_times', label: 'More regular meal times' },
    { value: 'smaller_portions', label: 'Smaller portions' },
    { value: 'more_water', label: 'Drink more water' },
];

export const MEALS_PER_DAY: Option[] = [
    { value: '2', label: '2 meals' },
    { value: '3', label: '3 meals' },
    { value: '3_snacks', label: '3 meals + snacks' },
    { value: '4_5', label: '4–5 smaller meals' },
    { value: 'recommend', label: 'Recommend for me' },
];

export const WEEKDAY_SCHEDULES: Option[] = [
    { value: 'regular', label: 'Regular day schedule' },
    { value: 'shifts', label: 'Shift work' },
    { value: 'variable', label: 'Different every day' },
    { value: 'nights', label: 'Night work' },
    { value: 'other', label: 'Other' },
];

export const EATING_LOCATIONS: Option[] = [
    { value: 'home', label: 'Mostly at home' },
    { value: 'work_school', label: 'At work / school' },
    { value: 'restaurants', label: 'Restaurants & cafes' },
    { value: 'mixed', label: 'A mix of places' },
];

export const PORTABLE_MEALS: Option[] = [
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
    { value: 'sometimes', label: 'Sometimes' },
];

export const BUDGET_TYPES: Option[] = [
    { value: 'daily', label: 'Daily budget' },
    { value: 'weekly', label: 'Weekly budget' },
    { value: 'none', label: 'No set budget' },
];

export const FOOD_SOURCES: Option[] = [
    { value: 'market', label: 'Local market / suq' },
    { value: 'supermarket', label: 'Supermarket' },
    { value: 'restaurants', label: 'Restaurants / take-away' },
    { value: 'mixed', label: 'A mix' },
];

export const INGREDIENT_AVAILABILITY: Option[] = [
    { value: 'easy', label: 'Easy to find' },
    { value: 'usually', label: 'Usually available' },
    { value: 'sometimes', label: 'Sometimes hard to find' },
    { value: 'hard', label: 'Often hard to find' },
];

export const COOK_OPTIONS: Option[] = [
    { value: 'self', label: 'I cook myself' },
    { value: 'family', label: 'Family member cooks' },
    {
        value: 'prepared',
        label: 'Mostly prepared / bought food',
        description: 'Skips kitchen questions',
    },
    { value: 'shared', label: 'Shared cooking' },
];

export const COOK_TIMES: Option[] = [
    { value: 'lt15', label: 'Under 15 minutes' },
    { value: '15_30', label: '15–30 minutes' },
    { value: '30_60', label: '30–60 minutes' },
    { value: 'gt60', label: 'Over an hour' },
];

export const KITCHEN_ITEMS: Option[] = [
    { value: 'basic', label: 'Basics (pots, pans)' },
    { value: 'fridge', label: 'Fridge' },
    { value: 'freezer', label: 'Freezer' },
    { value: 'stove', label: 'Stove / hotplate' },
    { value: 'oven', label: 'Oven' },
    { value: 'blender', label: 'Blender' },
    { value: 'none', label: 'None of these' },
];

export const REPETITION_OPTIONS: Option[] = [
    { value: 'fine', label: 'Fine with repeats' },
    {
        value: 'lunch_dinner_reuse',
        label: 'Cook once, reuse for lunch & dinner',
    },
    { value: 'varied', label: 'Need variety most days' },
    { value: 'batch', label: 'Batch cooking / meal prep' },
];

export const OBSTACLE_OPTIONS: Option[] = [
    { value: 'no_time', label: 'No time to cook' },
    { value: 'motivation', label: 'Staying motivated' },
    { value: 'cost', label: 'Cost of healthy food' },
    { value: 'family_meals', label: 'Family meals & expectations' },
    { value: 'eating_out', label: 'Eating out often' },
    { value: 'stress_eating', label: 'Stress or emotional eating' },
    { value: 'cravings', label: 'Cravings' },
    { value: 'cooking_skills', label: 'Limited cooking skills' },
    { value: 'irregular_schedule', label: 'Irregular schedule' },
    { value: 'other', label: 'Something else' },
];

export const SUPPORT_OPTIONS: Option[] = [
    { value: 'meal_reminders', label: 'Meal reminders' },
    { value: 'grocery_list', label: 'Grocery lists' },
    { value: 'simple_recipes', label: 'Simple recipes' },
    { value: 'family_involvement', label: 'Getting family involved' },
    { value: 'coach_checkins', label: 'Coach check-ins' },
    { value: 'progress_tracking', label: 'Progress tracking' },
    { value: 'fasting_adaptations', label: 'Fasting-day adaptations' },
    { value: 'eating_out_guidance', label: 'Eating-out guidance' },
];

/** Picker payload sent as a deferred prop. */
export interface PickerFood {
    id: number;
    name_en: string;
    name_am: string | null;
    allergens: string[];
    meal_types: string[];
}

export interface PickerCategory {
    name: string;
    foods: PickerFood[];
}

export interface FoodGroup {
    section: string;
    categories: PickerCategory[];
}

export interface PickerIngredient {
    id: number;
    name_en: string;
    name_am: string | null;
    allergens: string[];
    common_allergy_label: string | null;
}

export interface PickerProps {
    foodGroups: FoodGroup[];
    ingredients: PickerIngredient[];
}

export const FOOD_GROUP_LABELS: Record<string, string> = {
    traditional_ethiopian: 'Traditional Ethiopian',
    protein_rich: 'Protein-rich',
    other_staples: 'Other staples',
    fruit_and_veg: 'Fruit & vegetables',
};

export const MEAL_TYPE_LABELS: Record<string, string> = {
    breakfast: 'Breakfast',
    snack: 'Snack',
    lunch: 'Lunch',
    post_workout: 'Post-workout',
    dinner: 'Dinner',
    before_bed: 'Before bed',
};

export function defaultAnswers(): WizardAnswers {
    return {
        goal: {
            primary_goal: '',
            motivations: [],
            motivation_other: '',
            target_weight_kg: '',
            priorities: [],
        },
        body: {
            age: '',
            height_cm: '',
            weight_kg: '',
            sex: '',
            waist_cm: '',
            body_fat_percent: '',
            body_fat_is_estimate: true,
            weight_trend: '',
            medical_flags: [],
        },
        activity: {
            daily_activity: '',
            exercise_days: '',
            exercise_types: [],
            avg_daily_steps: '',
        },
        pace: { pace: '', target_date: '' },
        diet: {
            diet_type: '',
            diet_other: '',
            fasting: 'none',
            fasting_note: '',
            adapt_to_fasting: false,
            other_rules: '',
        },
        liked_foods: { liked_foods: [] },
        avoidances: {
            disliked_food_ids: [],
            allergen_codes: [],
            excluded_ingredient_ids: [],
            allergy_other: '',
            discomfort_ingredient_ids: [],
            discomfort_note: '',
        },
        habits: { usual_meals: [], diary: [], improvements: [] },
        schedule: {
            meals_per_day: '',
            wake_time: '',
            sleep_time: '',
            weekday_schedule: '',
            eating_location: '',
            portable_meals: '',
        },
        budget: {
            budget_type: '',
            budget_amount_etb: '',
            food_sources: [],
            ingredient_availability: '',
            allow_substitutions: true,
        },
        cooking: { cook: '', cook_time: '', kitchen: [], repetition: '' },
        obstacles: { obstacles: [], obstacle_other: '', support: [] },
    };
}

/**
 * Merge saved server answers over the defaults so every expected key exists.
 */
export function mergeAnswers(
    saved: Partial<Record<SectionKey, object>> | undefined,
): WizardAnswers {
    const merged = defaultAnswers();

    if (!saved) {
        return merged;
    }

    for (const key of Object.keys(merged) as SectionKey[]) {
        const section = saved[key];
        if (section && typeof section === 'object') {
            Object.assign(merged[key], section);
        }
    }

    return merged;
}

const NUMERIC_KEYS = new Set([
    'target_weight_kg',
    'age',
    'height_cm',
    'weight_kg',
    'waist_cm',
    'body_fat_percent',
    'avg_daily_steps',
    'budget_amount_etb',
]);

function cleanEntry(entry: unknown): unknown {
    if (entry !== null && typeof entry === 'object' && !Array.isArray(entry)) {
        const cleaned: Record<string, unknown> = {};
        for (const [key, raw] of Object.entries(
            entry as Record<string, unknown>,
        )) {
            if (key.startsWith('_')) {
                continue;
            }
            const value = cleanValue(key, raw);
            if (value === null) {
                continue;
            }
            cleaned[key] = value;
        }
        return cleaned;
    }

    return entry;
}

function cleanValue(key: string, value: unknown): unknown {
    if (value === '' || value === undefined || value === null) {
        return null;
    }

    if (typeof value === 'string' && NUMERIC_KEYS.has(key)) {
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
    }

    if (Array.isArray(value)) {
        return value.map(cleanEntry);
    }

    return value;
}

/**
 * Convert a section's form state into the request payload: empty strings and
 * invalid numerics become absent keys (so `nullable` and `prohibited_*` rules
 * behave correctly), numeric strings become numbers.
 */
export function toSectionPayload(
    data: object,
): Record<string, FormDataConvertible> {
    const payload: Record<string, unknown> = {};

    for (const [key, raw] of Object.entries(data)) {
        if (key.startsWith('_')) {
            continue;
        }
        const value = cleanValue(key, raw);
        if (value === null) {
            continue;
        }
        payload[key] = value;
    }

    return payload as Record<string, FormDataConvertible>;
}

/** Look up a human label for an option value. */
export function optionLabel(options: Option[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

const SECTION_OPTION_MAPS: Partial<Record<string, Option[]>> = {
    primary_goal: PRIMARY_GOALS,
    motivations: MOTIVATIONS,
    priorities: PRIORITIES,
    sex: SEX_OPTIONS,
    weight_trend: WEIGHT_TRENDS,
    medical_flags: MEDICAL_FLAGS,
    daily_activity: DAILY_ACTIVITY,
    exercise_days: EXERCISE_DAYS,
    exercise_types: EXERCISE_TYPES,
    pace: PACES,
    diet_type: DIET_TYPES,
    fasting: FASTING_OPTIONS,
    usual_meals: USUAL_MEALS,
    improvements: IMPROVEMENTS,
    meals_per_day: MEALS_PER_DAY,
    weekday_schedule: WEEKDAY_SCHEDULES,
    eating_location: EATING_LOCATIONS,
    portable_meals: PORTABLE_MEALS,
    budget_type: BUDGET_TYPES,
    food_sources: FOOD_SOURCES,
    ingredient_availability: INGREDIENT_AVAILABILITY,
    cook: COOK_OPTIONS,
    cook_time: COOK_TIMES,
    kitchen: KITCHEN_ITEMS,
    repetition: REPETITION_OPTIONS,
    obstacles: OBSTACLE_OPTIONS,
    support: SUPPORT_OPTIONS,
    allergen_codes: ALLERGEN_OPTIONS,
};

const FIELD_LABELS: Record<string, string> = {
    primary_goal: 'Goal',
    motivations: 'Motivations',
    motivation_other: 'Other motivation',
    target_weight_kg: 'Target weight',
    priorities: 'Priorities',
    age: 'Age',
    height_cm: 'Height',
    weight_kg: 'Weight',
    sex: 'Sex',
    waist_cm: 'Waist',
    body_fat_percent: 'Body fat',
    weight_trend: 'Recent weight trend',
    medical_flags: 'Medical considerations',
    daily_activity: 'Daily activity',
    exercise_days: 'Exercise days',
    exercise_types: 'Exercise types',
    avg_daily_steps: 'Average daily steps',
    pace: 'Pace',
    target_date: 'Target date',
    diet_type: 'Diet',
    diet_other: 'Other diet',
    fasting: 'Fasting',
    fasting_note: 'Fasting details',
    adapt_to_fasting: 'Adapt plan to fasting days',
    other_rules: 'Other rules',
    liked_foods: 'Liked foods',
    disliked_food_ids: 'Disliked foods',
    allergen_codes: 'Allergens',
    excluded_ingredient_ids: 'Ingredients to avoid',
    allergy_other: 'Other allergies',
    discomfort_ingredient_ids: 'Foods causing discomfort',
    discomfort_note: 'Discomfort notes',
    usual_meals: 'Usual meals',
    diary: 'Typical day of eating',
    improvements: 'Wants to improve',
    meals_per_day: 'Meals per day',
    wake_time: 'Wake time',
    sleep_time: 'Sleep time',
    weekday_schedule: 'Weekday schedule',
    eating_location: 'Where you eat',
    portable_meals: 'Can carry meals',
    budget_type: 'Budget',
    budget_amount_etb: 'Budget amount',
    food_sources: 'Where you shop',
    ingredient_availability: 'Ingredient availability',
    allow_substitutions: 'Open to substitutions',
    cook: 'Who cooks',
    cook_time: 'Cooking time',
    kitchen: 'Kitchen equipment',
    repetition: 'Food repetition',
    obstacles: 'Obstacles',
    obstacle_other: 'Other obstacle',
    support: 'Support wanted',
};

function formatValue(key: string, value: unknown): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    const options = SECTION_OPTION_MAPS[key];

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return 'None';
        }

        if (key === 'liked_foods' || key === 'diary') {
            return `${value.length} item${value.length === 1 ? '' : 's'}`;
        }

        if (key.endsWith('_ids')) {
            return `${value.length} selected`;
        }

        return value
            .map((item) =>
                options
                    ? optionLabel(options, String(item as string))
                    : String(item as string | number | boolean),
            )
            .join(', ');
    }

    // Below this point the value is a scalar.
    const scalar = value as string | number | boolean;

    if (options) {
        return optionLabel(options, String(scalar));
    }

    if (key === 'target_weight_kg' || key === 'weight_kg') {
        return `${scalar} kg`;
    }

    if (key === 'height_cm' || key === 'waist_cm') {
        return `${scalar} cm`;
    }

    if (key === 'budget_amount_etb') {
        return `${scalar} ETB`;
    }

    return String(scalar);
}

/**
 * Human-readable summary rows for a saved section, used by the review page.
 * Returns rows in the order fields appear in the saved payload.
 */
export function describeSection(
    section: SectionKey,
    data: Record<string, unknown> | undefined,
): { label: string; value: string }[] {
    if (!data) {
        return [];
    }

    const rows: { label: string; value: string }[] = [];

    for (const [key, raw] of Object.entries(data)) {
        const value = formatValue(key, raw);
        if (value === null) {
            continue;
        }
        rows.push({ label: FIELD_LABELS[key] ?? key, value });
    }

    return rows;
}
