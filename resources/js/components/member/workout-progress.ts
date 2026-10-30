import type { WorkoutSession, WorkoutSetEntry } from './types';

export function sessionVolume(session: WorkoutSession): number {
    return session.sets.reduce(
        (sum, set) => sum + (set.weight_kg ?? 0) * (set.reps ?? 0),
        0,
    );
}

export function sessionExerciseCount(session: WorkoutSession): number {
    return new Set(session.sets.map((set) => set.exercise_id)).size;
}

export function totalVolume(sessions: WorkoutSession[]): number {
    return sessions.reduce((sum, s) => sum + sessionVolume(s), 0);
}

export function sessionsThisWeek(
    sessions: WorkoutSession[],
    now: Date = new Date(),
): number {
    const day = new Date(now);
    day.setHours(0, 0, 0, 0);
    // Week starts Monday.
    const start = new Date(day);
    start.setDate(day.getDate() - ((day.getDay() + 6) % 7));

    return sessions.filter((s) => {
        const date = new Date(`${s.date}T12:00:00`);
        return date >= start && s.sets.length > 0;
    }).length;
}

/**
 * Personal record per exercise: highest weight (ties broken by reps).
 * Exercises logged without weight get a reps-only PR (weight_kg 0).
 */
export function prByExercise(
    sessions: WorkoutSession[],
): Record<string, { weight_kg: number; reps: number }> {
    const prs: Record<string, { weight_kg: number; reps: number }> = {};
    const repPrs: Record<string, number> = {};

    for (const session of sessions) {
        for (const set of session.sets) {
            const weight = set.weight_kg ?? 0;
            const reps = set.reps ?? 0;
            const current = prs[set.exercise_id];

            if (weight > 0) {
                if (
                    current === undefined ||
                    weight > current.weight_kg ||
                    (weight === current.weight_kg && reps > current.reps)
                ) {
                    prs[set.exercise_id] = { weight_kg: weight, reps };
                }
            } else if (reps > (repPrs[set.exercise_id] ?? 0)) {
                repPrs[set.exercise_id] = reps;
            }
        }
    }

    for (const [exerciseId, reps] of Object.entries(repPrs)) {
        prs[exerciseId] ??= { weight_kg: 0, reps };
    }

    return prs;
}

/**
 * Most recent session (excluding `excludeSessionId`) that logged the exercise.
 */
export function lastSessionWithExercise(
    sessions: WorkoutSession[],
    exerciseId: string,
    excludeSessionId?: number,
): WorkoutSession | null {
    const sorted = [...sessions].sort((a, b) => b.date.localeCompare(a.date));

    return (
        sorted.find(
            (s) =>
                s.id !== excludeSessionId &&
                s.sets.some((set) => set.exercise_id === exerciseId),
        ) ?? null
    );
}

/**
 * Past sessions that logged the exercise, most recent first.
 */
export function exerciseHistory(
    sessions: WorkoutSession[],
    exerciseId: string,
    excludeSessionId?: number,
    limit = 3,
): WorkoutSession[] {
    return [...sessions]
        .sort((a, b) => b.date.localeCompare(a.date))
        .filter(
            (s) =>
                s.id !== excludeSessionId &&
                s.sets.some((set) => set.exercise_id === exerciseId),
        )
        .slice(0, limit);
}

/**
 * Sets for one exercise inside a session, ordered by set index.
 */
export function setsForExercise(
    session: WorkoutSession,
    exerciseId: string,
): WorkoutSetEntry[] {
    return session.sets
        .filter((set) => set.exercise_id === exerciseId)
        .sort((a, b) => a.set_index - b.set_index);
}

export function setSummary(sets: WorkoutSetEntry[]): string {
    if (sets.length === 0) {
        return '';
    }
    const parts = sets
        .filter((s) => s.weight_kg !== null || s.reps !== null)
        .map((s) =>
            s.weight_kg === null || s.weight_kg === 0
                ? `${s.reps ?? 0} reps`
                : `${s.weight_kg}kg×${s.reps ?? 0}`,
        );
    return parts.length === 0 ? '' : parts.join(', ');
}
