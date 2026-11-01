import { describe, expect, it } from 'vitest';
import type { WorkoutSession } from './types';
import {
    consistencyMap,
    exerciseHistory,
    exerciseSeries,
    lastSessionWithExercise,
    prByExercise,
    sessionExerciseCount,
    sessionVolume,
    sessionsThisWeek,
    setsForExercise,
    setSummary,
    totalVolume,
    volumeByBodyPart,
    weeklyStats,
    weeklyVolumeSeries,
} from './workout-progress';

function session(
    id: number,
    date: string,
    sets: WorkoutSession['sets'],
): WorkoutSession {
    return { id, exercise_playlist_id: null, date, sets };
}

function set(
    id: number,
    exerciseId: string,
    index: number,
    weight: number | null,
    reps: number | null,
): WorkoutSession['sets'][number] {
    return {
        id,
        workout_session_id: 0,
        exercise_id: exerciseId,
        set_index: index,
        weight_kg: weight,
        reps,
    };
}

const SESSIONS: WorkoutSession[] = [
    session(2, '2026-10-20', [
        set(3, 'bench-press', 0, 70, 8),
        set(4, 'bench-press', 1, 70, 6),
    ]),
    session(1, '2026-10-15', [
        set(1, 'bench-press', 0, 60, 10),
        set(2, 'squat', 0, 80, 5),
    ]),
];

describe('volume helpers', () => {
    it('computes per-session and total volume', () => {
        expect(sessionVolume(SESSIONS[0])).toBe(980);
        expect(totalVolume(SESSIONS)).toBe(980 + 600 + 400);
    });
});

describe('sessionExerciseCount', () => {
    it('counts distinct exercises with logged sets', () => {
        expect(sessionExerciseCount(SESSIONS[0])).toBe(1);
        expect(sessionExerciseCount(SESSIONS[1])).toBe(2);
        expect(sessionExerciseCount(session(9, '2026-10-22', []))).toBe(0);
    });
});

describe('sessionsThisWeek', () => {
    it('counts sessions with sets inside the current week', () => {
        const sessions = [
            session(1, '2026-10-19', [set(1, 'a', 0, 10, 10)]), // Monday
            session(2, '2026-10-21', [set(2, 'a', 0, 10, 10)]), // Wednesday
            session(3, '2026-10-12', [set(3, 'a', 0, 10, 10)]), // previous week
            session(4, '2026-10-22', []), // empty session doesn't count
        ];
        expect(
            sessionsThisWeek(sessions, new Date('2026-10-24T12:00:00')),
        ).toBe(2);
    });
});

describe('prByExercise', () => {
    it('picks the heaviest weight per exercise', () => {
        const prs = prByExercise(SESSIONS);
        expect(prs['bench-press']).toEqual({ weight_kg: 70, reps: 8 });
        expect(prs['squat']).toEqual({ weight_kg: 80, reps: 5 });
    });

    it('prefers more reps on equal weight', () => {
        const prs = prByExercise([
            session(1, '2026-10-01', [set(1, 'a', 0, 50, 5)]),
            session(2, '2026-10-02', [set(2, 'a', 0, 50, 9)]),
        ]);
        expect(prs['a']).toEqual({ weight_kg: 50, reps: 9 });
    });

    it('tracks a reps-only PR for unweighted sets', () => {
        const prs = prByExercise([
            session(1, '2026-10-01', [set(1, 'push-up', 0, null, 15)]),
            session(2, '2026-10-02', [set(2, 'push-up', 0, null, 20)]),
        ]);
        expect(prs['push-up']).toEqual({ weight_kg: 0, reps: 20 });
    });

    it('prefers a weighted PR over a reps-only PR', () => {
        const prs = prByExercise([
            session(1, '2026-10-01', [set(1, 'push-up', 0, null, 30)]),
            session(2, '2026-10-02', [set(2, 'push-up', 0, 10, 8)]),
        ]);
        expect(prs['push-up']).toEqual({ weight_kg: 10, reps: 8 });
    });
});

describe('history helpers', () => {
    it('finds the most recent session containing an exercise', () => {
        const last = lastSessionWithExercise(SESSIONS, 'bench-press');
        expect(last?.id).toBe(2);
    });

    it('excludes a given session', () => {
        const last = lastSessionWithExercise(SESSIONS, 'bench-press', 2);
        expect(last?.id).toBe(1);
    });

    it('returns null when the exercise was never logged', () => {
        expect(lastSessionWithExercise(SESSIONS, 'deadlift')).toBeNull();
    });

    it('lists history most recent first, limited', () => {
        const history = exerciseHistory(SESSIONS, 'bench-press', undefined, 1);
        expect(history.map((s) => s.id)).toEqual([2]);
    });
});

describe('exerciseSeries', () => {
    it('returns the best set per session, oldest first, with e1rm', () => {
        const series = exerciseSeries(SESSIONS, 'bench-press');
        expect(series).toEqual([
            {
                date: '2026-10-15',
                weight_kg: 60,
                reps: 10,
                e1rm: 80,
            },
            {
                date: '2026-10-20',
                weight_kg: 70,
                reps: 8,
                e1rm: 88.7,
            },
        ]);
    });

    it('reports null weight and e1rm for unweighted days', () => {
        const series = exerciseSeries(
            [session(1, '2026-10-01', [set(1, 'push-up', 0, null, 15)])],
            'push-up',
        );
        expect(series).toEqual([
            { date: '2026-10-01', weight_kg: null, reps: 15, e1rm: null },
        ]);
    });

    it('skips sessions that never logged the exercise', () => {
        expect(exerciseSeries(SESSIONS, 'deadlift')).toEqual([]);
        expect(
            exerciseSeries(
                [session(1, '2026-10-01', [set(1, 'squat', 0, 80, 5)])],
                'bench-press',
            ),
        ).toEqual([]);
    });
});

describe('weeklyVolumeSeries', () => {
    it('buckets volume into Monday-anchored weeks', () => {
        // 'now' is a Saturday; week of Oct 19 starts Monday Oct 19.
        const series = weeklyVolumeSeries(
            SESSIONS,
            2,
            new Date('2026-10-24T12:00:00'),
        );
        expect(series).toHaveLength(2);
        // 2026-10-15 is in the Oct 12 week: 600 + 400 = 1000.
        expect(series[0].volume).toBe(1000);
        // 2026-10-20 is in the Oct 19 week: 980.
        expect(series[1].volume).toBe(980);
    });

    it('reports zero for empty weeks', () => {
        const series = weeklyVolumeSeries([], 3, new Date('2026-10-24'));
        expect(series).toHaveLength(3);
        expect(series.every((b) => b.volume === 0)).toBe(true);
    });
});

describe('weeklyStats', () => {
    it('splits sessions and volume into this week vs last', () => {
        // Now = Saturday 2026-10-24; week starts Monday Oct 19.
        const stats = weeklyStats(SESSIONS, new Date('2026-10-24T12:00:00'));
        expect(stats.current).toEqual({ sessions: 1, volume: 980 });
        expect(stats.previous).toEqual({ sessions: 1, volume: 1000 });
    });

    it('ignores empty sessions', () => {
        const stats = weeklyStats(
            [session(1, '2026-10-20', [])],
            new Date('2026-10-24T12:00:00'),
        );
        expect(stats.current).toEqual({ sessions: 0, volume: 0 });
    });
});

describe('volumeByBodyPart', () => {
    it('groups lifted volume by muscle, best first', () => {
        const parts = volumeByBodyPart(SESSIONS, {
            'bench-press': { body_part: 'chest' },
            squat: { body_part: 'upper legs' },
        });
        expect(parts).toEqual([
            { part: 'chest', volume: 980 + 600 },
            { part: 'upper legs', volume: 400 },
        ]);
    });

    it('buckets unknown exercises under other', () => {
        const parts = volumeByBodyPart(SESSIONS, {
            'bench-press': { body_part: 'chest' },
        });
        expect(parts[1]).toEqual({ part: 'other', volume: 400 });
    });
});

describe('consistencyMap', () => {
    it('returns a zero-filled day map oldest first', () => {
        const map = consistencyMap(
            SESSIONS,
            3,
            new Date('2026-10-20T12:00:00'),
        );
        expect(map).toEqual([
            { date: '2026-10-18', volume: 0 },
            { date: '2026-10-19', volume: 0 },
            { date: '2026-10-20', volume: 980 },
        ]);
    });
});

describe('setsForExercise / setSummary', () => {
    it('returns sets sorted by index for one exercise', () => {
        const sets = setsForExercise(SESSIONS[0], 'bench-press');
        expect(sets.map((s) => s.set_index)).toEqual([0, 1]);
        expect(setSummary(sets)).toBe('70kg×8, 70kg×6');
    });

    it('formats unweighted sets as reps', () => {
        expect(
            setSummary([
                set(1, 'push-up', 0, null, 15),
                set(2, 'push-up', 1, null, 12),
            ]),
        ).toBe('15 reps, 12 reps');
    });
});
