export type AdminProgram = {
    id: number;
    name: string;
    price_etb: number;
    is_free: boolean;
    workouts_count: number;
    claims_count: number;
    share_url: string | null;
    published: boolean;
};

export type AdminProgramExercise = {
    slug: string;
    sets: number | null;
    reps: number | null;
};

export type AdminProgramWorkout = {
    id?: number;
    title: string;
    exercises: AdminProgramExercise[];
};

export type AdminProgramDetail = {
    id: number;
    name: string;
    name_am: string | null;
    description: string | null;
    description_am: string | null;
    image_url: string | null;
    highlights: string[];
    highlights_am: string[];
    price_etb: number;
    share_url: string | null;
    published: boolean;
    workouts: AdminProgramWorkout[];
};

export type AdminMealProgram = {
    id: number;
    name: string;
    price_etb: number;
    is_free: boolean;
    days_count: number;
    meals_count: number;
    claims_count: number;
    share_url: string | null;
    published: boolean;
};

export type AdminMealProgramItem = {
    food_id: number;
    portion_note: string | null;
};

export type AdminMealProgramMeal = {
    id?: number;
    meal_type: string;
    items: AdminMealProgramItem[];
};

export type AdminMealProgramDay = {
    meals: AdminMealProgramMeal[];
};

export type AdminMealProgramDetail = {
    id: number;
    name: string;
    name_am: string | null;
    description: string | null;
    description_am: string | null;
    image_url: string | null;
    highlights: string[];
    highlights_am: string[];
    price_etb: number;
    share_url: string | null;
    published: boolean;
    days: AdminMealProgramDay[];
};

export type MealTypeOption = {
    value: string;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
