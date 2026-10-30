import { describe, expect, it } from 'vitest';
import { tracksWeight, UNWEIGHTED_EQUIPMENT } from './exercise-data';

describe('tracksWeight', () => {
    it('tracks weight for loadable equipment', () => {
        for (const equipment of [
            'barbell',
            'ez barbell',
            'olympic barbell',
            'trap bar',
            'dumbbell',
            'kettlebell',
            'cable',
            'leverage machine',
            'smith machine',
            'sled machine',
            'weighted',
        ]) {
            expect(tracksWeight(equipment)).toBe(true);
        }
    });

    it('skips weight for non-loadable equipment', () => {
        for (const equipment of UNWEIGHTED_EQUIPMENT) {
            expect(tracksWeight(equipment)).toBe(false);
        }
    });

    it('defaults to weighted for missing or unknown equipment', () => {
        expect(tracksWeight(undefined)).toBe(true);
        expect(tracksWeight(null)).toBe(true);
        expect(tracksWeight('')).toBe(true);
        expect(tracksWeight('anti-gravity boots')).toBe(true);
    });
});
