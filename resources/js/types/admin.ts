export type AdminStats = {
    total_users: number;
    paid_users: number;
    revenue_etb: number;
    revenue_7d: number;
    revenue_prev_7d: number;
    new_users_7d: number;
    new_users_prev_7d: number;
    conversion_pct: number;
};

export type AdminDashboardSeries = {
    date: string;
    signups: number;
    revenue: number;
};

export type AdminDashboardAttention = {
    pending_payments: number;
    failed_payments_7d: number;
};

export type AdminUser = {
    id: number;
    name: string;
    username: string | null;
    phone: string | null;
    email: string | null;
    telegram_username: string | null;
    role: string;
    email_verified_at: string | null;
    successful_payments_count?: number;
    created_at: string;
    nutrition_profile?: NutritionProfileSummary | null;
    payments?: AdminPayment[];
};

export type NutritionProfileSummary = {
    id: number;
    status: string;
    completed_at: string | null;
    goal: Record<string, unknown> | null;
    body: Record<string, unknown> | null;
    diet: Record<string, unknown> | null;
};

export type AdminPayment = {
    id: number;
    user_id: number;
    provider: 'chapa' | 'verify_checkout' | 'jami';
    tx_ref: string;
    deposit_id: string | null;
    amount: string;
    currency: string;
    status:
        | 'pending'
        | 'success'
        | 'failed'
        | 'expired'
        | 'cancelled'
        | 'review_required'
        | 'refunded';
    email: string;
    first_name: string | null;
    last_name: string | null;
    provider_response: Record<string, unknown> | null;
    paid_at: string | null;
    refunded_at: string | null;
    created_at: string;
    user?: { id: number; name: string; email: string | null } | null;
};

export type AdminFood = {
    id: number;
    name_en: string;
    name_am: string | null;
    category: string;
    kcal_per_100g: string | null;
    protein_g: string | null;
    carbs_g: string | null;
    fat_g: string | null;
    fibre_g?: string | null;
    image_url?: string | null;
    meal_types?: string[];
    allergens?: string[];
    is_fasting_friendly?: boolean;
    fish_ok_fasting?: boolean;
    is_vegetarian?: boolean;
    is_vegan?: boolean;
    contains_meat?: boolean;
    goal_fat_loss?: boolean;
    goal_muscle_gain?: boolean;
    ingredient_ids?: number[];
    review_needed: boolean;
    data_fix_note?: string | null;
};

export type AdminOption = { value: string; label: string };
export type IngredientOption = {
    id: number;
    name_en: string;
    name_am: string | null;
};

export type AdminIngredient = {
    id: number;
    slug: string;
    name_en: string;
    name_am: string;
    category: string;
    allergens?: string[];
    common_allergy_label: string | null;
    show_in_onboarding: boolean;
    is_vegan: boolean;
};

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

export type BroadcastMediaItem = {
    kind: 'photo' | 'video' | 'document' | 'animation';
    source: 'path' | 'url' | 'file_id';
    value: string;
    url?: string | null;
    file_id?: string | null;
    caption_html?: string;
    caption_telegram?: string;
};

export type BroadcastButton = {
    text: string;
    kind: string;
    url?: string;
    query?: string;
    copy_text?: string;
    action?: string;
    toast?: string;
    show_alert?: boolean;
    forward_text?: string;
    request_write_access?: boolean;
    allow_user_chats?: boolean;
    allow_bot_chats?: boolean;
    allow_group_chats?: boolean;
    allow_channel_chats?: boolean;
};

export type BroadcastPoll = {
    question: string;
    options: string[];
    type: 'regular' | 'quiz';
    is_anonymous: boolean;
    allows_multiple_answers: boolean;
    correct_option_id?: number | null;
    explanation?: string;
    open_period?: number | null;
};

export type BroadcastAudienceConfig = {
    everyone: boolean;
    segments: string[];
    user_ids: number[];
};

export type BroadcastRun = {
    id: number;
    trigger: string;
    recipients_count: number;
    sent_count: number;
    failed_count: number;
    blocked_count: number;
    deleted_count: number;
    started_at: string | null;
    finished_at: string | null;
};

export type AdminBroadcast = {
    id: number;
    name: string;
    type: string;
    status: string;
    recurrence: string;
    audience: string;
    next_run_at: string | null;
    last_run_at: string | null;
    last_run: {
        id: number;
        recipients_count: number;
        sent_count: number;
        failed_count: number;
        blocked_count: number;
    } | null;
    body_html?: string | null;
    telegram_text?: string | null;
    media?: BroadcastMediaItem[] | null;
    buttons?: BroadcastButton[][] | null;
    poll?: BroadcastPoll | null;
    options?: { disable_notification: boolean; protect_content: boolean } | null;
    audience_config?: BroadcastAudienceConfig;
    scheduled_at?: string | null;
    editable?: boolean;
    runs?: BroadcastRun[];
};

export type BroadcastDelivery = {
    id: number;
    run_id: number;
    status: string;
    error: string | null;
    sent_at: string | null;
    deleted_at: string | null;
    user: {
        id: number;
        name: string;
        username: string | null;
        telegram_username: string | null;
        phone: string | null;
    } | null;
};

export type BroadcastSegment = {
    key: string;
    label: string;
    count: number;
};

export type BroadcastUserHit = {
    id: number;
    label: string;
    detail: string;
};
