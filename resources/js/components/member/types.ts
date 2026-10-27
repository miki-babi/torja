export type PlanMealItem = {
    name: string;
    portion_note: string | null;
    food_id?: number | null;
    image_url?: string | null;
    macros?: {
        kcal_per_100g: number | null;
        protein_g: number | null;
        carbs_g: number | null;
        fat_g: number | null;
    } | null;
    swapped_from?: { name: string; reason: string | null } | null;
    excluded?: boolean;
    item_id?: number;
    chosen?: boolean;
    original_name?: string;
    original_food_id?: number | null;
    original_image_url?: string | null;
    original_macros?: {
        kcal_per_100g: number | null;
        protein_g: number | null;
        carbs_g: number | null;
        fat_g: number | null;
    } | null;
    swap_options?: {
        food_id: number;
        name: string;
        image_url?: string | null;
        reason: string | null;
        macros?: {
            kcal_per_100g: number | null;
            protein_g: number | null;
            carbs_g: number | null;
            fat_g: number | null;
        } | null;
        excluded: boolean;
    }[];
};

export type PlanMeal = {
    id?: number;
    type: string;
    items: PlanMealItem[];
};

export type PlanDay = {
    plan_name: string;
    day: number;
    meals: PlanMeal[];
    excluded_count: number;
};

export type NutritionTargets = {
    bmr: number;
    tdee: number;
    kcal_target: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
};

export type DiaryEntry = {
    id: string;
    name: string;
    detail: string;
    kcal: number;
    protein: number;
    carbs: number;
    fat: number;
    photo?: string;
    photos?: string[];
};

export type DiaryTotals = Pick<
    DiaryEntry,
    'kcal' | 'protein' | 'carbs' | 'fat'
>;

export type FoodSearchResult = {
    id: number;
    name_en: string;
    kcal_per_100g: number | null;
    protein_g: number | null;
    carbs_g: number | null;
    fat_g: number | null;
};

export type WorkoutSetEntry = {
    id: number;
    exercise_id: string;
    set_index: number;
    weight_kg: number | null;
    reps: number | null;
};

export type WorkoutSession = {
    id: number;
    exercise_playlist_id: number | null;
    date: string;
    sets: WorkoutSetEntry[];
};

export type Habit = {
    id: number;
    name: string;
    dates: string[];
    streak: number;
    total: number;
};

export type HomeHabit = {
    id: number;
    name: string;
    done: boolean;
};

export type ExercisePlaylist = {
    id: number;
    name: string;
    exercise_ids: string[];
    generated_for?: string | null;
    share_url?: string | null;
    can_share?: boolean;
    program_id?: number | null;
    program_name?: string | null;
    workout_title?: string | null;
};

export const MEAL_TYPE_LABELS: Record<string, string> = {
    breakfast: 'Breakfast',
    snack: 'Snack',
    lunch: 'Lunch',
    post_workout: 'Post-workout',
    dinner: 'Dinner',
    before_bed: 'Before bed',
};

export const MEAL_TYPE_TIMES: Record<string, string> = {
    breakfast: '8:30',
    snack: '15:00',
    lunch: '12:30',
    post_workout: '17:00',
    dinner: '19:30',
    before_bed: '21:30',
};

export function mealTypeLabel(type: string): string {
    return MEAL_TYPE_LABELS[type] ?? type;
}

export function mealItemSummary(meal: PlanMeal): string {
    return meal.items.map((item) => item.name).join(', ');
}
