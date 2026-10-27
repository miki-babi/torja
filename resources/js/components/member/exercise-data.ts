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
    attribution: string;
};

/** slug → {name, detail} map passed by controllers for ids on the page. */
export type ExerciseSummaries = Record<
    string,
    { name: string; detail: string }
>;

export type BodyPart = {
    id: string;
    label: string;
    count?: number;
};

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
