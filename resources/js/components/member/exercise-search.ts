import type { ExerciseSummary } from '@/components/member/exercise-data';
import { search as exerciseSearch } from '@/routes/member/exercises';

export type ExerciseSearchResponse = {
    exercises: ExerciseSummary[];
    total: number;
};

/**
 * Query member.exercises.search. Empty filter values are dropped from the
 * query string.
 */
export async function searchExercises(params: {
    q?: string;
    body_part?: string[];
    equipment?: string[];
    level?: string;
    offset?: number;
    limit?: number;
}): Promise<ExerciseSearchResponse> {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value === '' || value === undefined) {
            continue;
        }
        if (Array.isArray(value)) {
            value.forEach((v) => query.append(`${key}[]`, v));
        } else {
            query.set(key, String(value));
        }
    }

    const response = await fetch(`${exerciseSearch().url}?${query}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    return response.json() as Promise<ExerciseSearchResponse>;
}
