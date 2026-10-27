import { describe, expect, it } from 'vitest';
import type { WorkoutSession } from './types';
import {
    exerciseHistory,
    lastSessionWithExercise,
    prByExercise,
    sessionVolume,
    sessionsThisWeek,
    setsForExercise,
    setSummary,
    totalVolume,
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

describe('setsForExercise / setSummary', () => {
    it('returns sets sorted by index for one exercise', () => {
        const sets = setsForExercise(SESSIONS[0], 'bench-press');
        expect(sets.map((s) => s.set_index)).toEqual([0, 1]);
        expect(setSummary(sets)).toBe('70kg×8, 70kg×6');
    });
});
