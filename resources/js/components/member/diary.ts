import {
    destroy as destroyDiaryEntry,
    store as storeDiaryEntry,
} from '@/routes/member/diary';

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export type DiaryEntryPayload = {
    logged_on: string;
    name: string;
    detail: string;
    kcal: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    food_id?: number | null;
    source: string;
    had_photos?: boolean;
};

export type StoredDiaryEntry = {
    id: number;
    name: string;
    detail: string | null;
    kcal: string | null;
    protein_g: string | null;
    carbs_g: string | null;
    fat_g: string | null;
    food_id: number | null;
    source: string;
};

/**
 * Persist a food-diary entry for a date — shared by the nutrition page and
 * the member meal-program viewer. Returns the created entry so callers can
 * undo it later.
 */
export async function logDiaryEntry(
    entry: DiaryEntryPayload,
): Promise<StoredDiaryEntry> {
    const response = await fetch(storeDiaryEntry().url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(entry),
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    const data = (await response.json()) as { entry: StoredDiaryEntry };

    return data.entry;
}

/**
 * Remove a diary entry — used to undo an "ate this" alternative.
 */
export async function deleteDiaryEntry(entryId: number): Promise<void> {
    const response = await fetch(destroyDiaryEntry(entryId).url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }
}
