import {
    Accessibility,
    Activity,
    BicepsFlexed,
    CircleDot,
    Dumbbell,
    Footprints,
    Grip,
    Hand,
    HeartPulse,
    PersonStanding,
    Weight,
    Zap,
    type LucideIcon,
} from 'lucide-react';

export type ExerciseSummary = {
    slug: string;
    name: string;
    detail: string;
    body_part: string;
    equipment: string;
    target: string;
    level: string;
    image_url: string;
};

export type ExerciseDetail = ExerciseSummary & {
    gif_url: string;
    muscle_group: string;
    secondary_muscles: string[];
    instruction_steps: string[];
    location: string;
    exercise_type: string;
    movement_pattern: string | null;
};

/** slug → {name, detail, equipment, image_url} map passed by controllers for ids on the page. */
export type ExerciseSummaries = Record<
    string,
    { name: string; detail: string; image_url?: string; equipment?: string }
>;

export type BodyPart = {
    id: string;
    label: string;
    count?: number;
};

/** Equipment with no meaningful load to record — logging asks for reps only. */
export const UNWEIGHTED_EQUIPMENT: ReadonlySet<string> = new Set([
    'assisted',
    'band',
    'body weight',
    'bosu ball',
    'elliptical machine',
    'hammer',
    'medicine ball',
    'resistance band',
    'roller',
    'rope',
    'skierg machine',
    'stability ball',
    'stationary bike',
    'stepmill machine',
    'tire',
    'upper body ergometer',
    'wheel roller',
]);

/**
 * Whether sets for this equipment capture a kg weight. Unknown equipment
 * defaults to weighted.
 */
export function tracksWeight(equipment: string | null | undefined): boolean {
    return !UNWEIGHTED_EQUIPMENT.has(equipment ?? '');
}

export const BODY_PART_ICONS: Record<string, LucideIcon> = {
    '': PersonStanding, // "Full body" tile — empty id means no filter
    chest: Dumbbell,
    back: Activity,
    shoulders: Zap,
    arms: BicepsFlexed,
    'upper arms': BicepsFlexed,
    'lower arms': Hand,
    waist: CircleDot,
    'upper legs': Weight,
    'lower legs': Footprints,
    cardio: HeartPulse,
    neck: Accessibility,
};

export function bodyPartIcon(id: string): LucideIcon {
    return BODY_PART_ICONS[id] ?? Grip;
}

/** Muscle illustration for a body part; null keeps the Lucide icon. */
export const BODY_PART_IMAGES: Record<string, string> = {
    chest: '/images/target-muscles/chest.png',
    back: '/images/target-muscles/back.png',
    shoulders: '/images/target-muscles/shoulder.png',
    arms: '/images/target-muscles/arms.png',
    'upper arms': '/images/target-muscles/arms.png',
    'lower arms': '/images/target-muscles/arms.png',
    waist: '/images/target-muscles/waist.png',
    'upper legs': '/images/target-muscles/upperleg.png',
    'lower legs': '/images/target-muscles/lowerleg.png',
    neck: '/images/target-muscles/neck.png',
};

export function bodyPartImage(id: string): string | null {
    return BODY_PART_IMAGES[id] ?? null;
}

/**
 * Display name for an exercise id from a summaries map, falling back to the
 * raw id when the exercise isn't loaded (e.g. a legacy or removed id).
 */
export function exerciseName(summaries: ExerciseSummaries, id: string): string {
    return summaries[id]?.name ?? id;
}

/**
 * Detail line ("Barbell · Pectorals") for an exercise id, or null.
 */
export function exerciseDetail(
    summaries: ExerciseSummaries,
    id: string,
): string | null {
    return summaries[id]?.detail ?? null;
}
