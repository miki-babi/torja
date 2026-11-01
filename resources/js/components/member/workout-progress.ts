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

/**
 * Best set per session for one exercise, oldest first. Days with weighted
 * sets report the heaviest weight (ties broken by reps); days logged
 * without weight report the top reps with a null weight so the caller can
 * chart reps instead.
 */
export function exerciseSeries(
    sessions: WorkoutSession[],
    exerciseId: string,
): {
    date: string;
    weight_kg: number | null;
    reps: number | null;
    e1rm: number | null;
}[] {
    const points: {
        date: string;
        weight_kg: number | null;
        reps: number | null;
        e1rm: number | null;
    }[] = [];

    for (const session of [...sessions].sort((a, b) =>
        a.date.localeCompare(b.date),
    )) {
        const sets = session.sets.filter(
            (set) => set.exercise_id === exerciseId,
        );
        if (sets.length === 0) {
            continue;
        }

        const best = sets.reduce((top, set) => {
            const weight = set.weight_kg ?? 0;
            const reps = set.reps ?? 0;
            const topWeight = top.weight_kg ?? 0;
            const topReps = top.reps ?? 0;
            return weight > topWeight ||
                (weight === topWeight && reps > topReps)
                ? set
                : top;
        });

        const weight = best.weight_kg ?? 0;
        const reps = best.reps ?? 0;
        points.push({
            date: session.date,
            weight_kg: weight > 0 ? weight : null,
            reps: best.reps,
            // Epley estimate — only meaningful for weighted sets.
            e1rm:
                weight > 0
                    ? Math.round(weight * (1 + reps / 30) * 10) / 10
                    : null,
        });
    }

    return points;
}

/**
 * Total lifted volume per calendar week (weeks start Monday), oldest
 * first. `weeks` buckets always come back — empty weeks report zero so
 * the chart keeps a continuous axis.
 */
export function weeklyVolumeSeries(
    sessions: WorkoutSession[],
    weeks = 10,
    now: Date = new Date(),
): { label: string; volume: number }[] {
    const day = new Date(now);
    day.setHours(0, 0, 0, 0);
    // Week starts Monday.
    const weekStart = new Date(day);
    weekStart.setDate(day.getDate() - ((day.getDay() + 6) % 7));

    const buckets = Array.from({ length: weeks }, (_, i) => {
        const start = new Date(weekStart);
        start.setDate(weekStart.getDate() - (weeks - 1 - i) * 7);
        return {
            start,
            end: new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000),
            label: start.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
            }),
            volume: 0,
        };
    });

    for (const session of sessions) {
        const date = new Date(`${session.date}T12:00:00`);
        const bucket = buckets.find((b) => date >= b.start && date < b.end);
        if (bucket) {
            bucket.volume += sessionVolume(session);
        }
    }

    return buckets.map(({ label, volume }) => ({ label, volume }));
}

/**
 * This week vs last week, Monday-anchored: sessions with sets and total
 * volume. Drives the "vs last week" deltas under the progress stats.
 */
export function weeklyStats(
    sessions: WorkoutSession[],
    now: Date = new Date(),
): {
    current: { sessions: number; volume: number };
    previous: { sessions: number; volume: number };
} {
    const day = new Date(now);
    day.setHours(0, 0, 0, 0);
    // Week starts Monday.
    const weekStart = new Date(day);
    weekStart.setDate(day.getDate() - ((day.getDay() + 6) % 7));
    const lastWeekStart = new Date(weekStart);
    lastWeekStart.setDate(weekStart.getDate() - 7);

    const stats = {
        current: { sessions: 0, volume: 0 },
        previous: { sessions: 0, volume: 0 },
    };

    for (const session of sessions) {
        if (session.sets.length === 0) {
            continue;
        }
        const date = new Date(`${session.date}T12:00:00`);
        const bucket =
            date >= weekStart
                ? stats.current
                : date >= lastWeekStart
                  ? stats.previous
                  : null;
        if (bucket !== null) {
            bucket.sessions += 1;
            bucket.volume += sessionVolume(session);
        }
    }

    return stats;
}

/**
 * Lifted volume grouped by muscle (body part), best first. `summaries`
 * supplies the part per exercise; sets whose exercise isn't in the map
 * fall under "other".
 */
export function volumeByBodyPart(
    sessions: WorkoutSession[],
    summaries: Record<string, { body_part?: string }>,
): { part: string; volume: number }[] {
    const totals: Record<string, number> = {};

    for (const session of sessions) {
        for (const set of session.sets) {
            const part = summaries[set.exercise_id]?.body_part ?? 'other';
            totals[part] =
                (totals[part] ?? 0) + (set.weight_kg ?? 0) * (set.reps ?? 0);
        }
    }

    return Object.entries(totals)
        .map(([part, volume]) => ({ part, volume }))
        .sort((a, b) => b.volume - a.volume);
}

/**
 * Per-day lifted volume for the last `days` days, oldest first. Powers
 * the consistency heatmap — a zero-volume day means no logged sets.
 */
export function consistencyMap(
    sessions: WorkoutSession[],
    days = 84,
    now: Date = new Date(),
): { date: string; volume: number }[] {
    const end = new Date(now);
    end.setHours(0, 0, 0, 0);

    const byDate: Record<string, number> = {};
    for (const session of sessions) {
        byDate[session.date] =
            (byDate[session.date] ?? 0) + sessionVolume(session);
    }

    return Array.from({ length: days }, (_, i) => {
        const date = new Date(end);
        date.setDate(end.getDate() - (days - 1 - i));
        // Local-date key — session dates are stored as local Y-m-d.
        const key = [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
        return { date: key, volume: byDate[key] ?? 0 };
    });
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
