export type PlanMealItem = {
    name: string;
    portion_note: string | null;
    item_id?: number;
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

export type WorkoutSetEntry = {
    id: number;
    workout_session_id: number;
    exercise_id: string;
    set_index: number;
    weight_kg: number | null;
    reps: number | null;
};

export type WorkoutSession = {
    id: number;
    exercise_playlist_id: number | null;
    date: string;
    has_recap: boolean;
    sets: WorkoutSetEntry[];
};

export type RecapUser = {
    id: number;
    name: string | null;
    avatar: string | null;
};

export type RecapComment = {
    id: number;
    body: string;
    created_at: string | null;
    user: RecapUser;
    can_delete: boolean;
};

export type RecapExerciseRow = {
    name: string;
    sets: number;
    weight_kg: number | null;
    reps: number | null;
};

export type RecapSummary = {
    title: string | null;
    duration_minutes: number | null;
    exercise_count: number;
    set_count: number;
    volume_kg: number;
    exercises: RecapExerciseRow[];
};

export type Recap = {
    id: number;
    caption: string | null;
    image_url: string | null;
    created_at: string | null;
    user: RecapUser;
    summary: RecapSummary;
    bravos_count: number;
    bravoed: boolean;
    comments_count: number;
    comments: RecapComment[];
    can_edit: boolean;
    can_delete: boolean;
};

export type RecapFeedPage = {
    recaps: Recap[];
    next_cursor: number | null;
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
