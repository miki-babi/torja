import { describe, expect, it } from 'vitest';
import {
    bmiBodyFat,
    bmiCategory,
    bodyFatCategory,
    calculateBmi,
    cmToFeetInches,
    fatMassKg,
    feetInchesToCm,
    kgToPounds,
    leanMassKg,
    navyBodyFat,
    poundsToKg,
} from './body-metrics';

describe('unit conversions', () => {
    it('converts feet and inches to centimeters', () => {
        expect(feetInchesToCm(5, 9)).toBeCloseTo(175.26, 2);
    });

    it('converts centimeters to feet and inches', () => {
        const { feet, inches } = cmToFeetInches(175);
        expect(feet).toBe(5);
        expect(inches).toBeCloseTo(8.9, 1);
    });

    it('converts pounds to kilograms', () => {
        expect(poundsToKg(154)).toBeCloseTo(69.85, 2);
    });

    it('converts kilograms to pounds', () => {
        expect(kgToPounds(70)).toBeCloseTo(154.32, 2);
    });
});

describe('calculateBmi', () => {
    it('calculates BMI from metric inputs', () => {
        expect(calculateBmi(70, 175)).toBeCloseTo(22.86, 2);
    });

    it('returns null for non-positive inputs', () => {
        expect(calculateBmi(0, 175)).toBeNull();
        expect(calculateBmi(70, 0)).toBeNull();
    });
});

describe('bmiCategory', () => {
    it('classifies boundary values', () => {
        expect(bmiCategory(18.4)).toBe('Underweight');
        expect(bmiCategory(18.5)).toBe('Normal');
        expect(bmiCategory(24.9)).toBe('Normal');
        expect(bmiCategory(25)).toBe('Overweight');
        expect(bmiCategory(29.9)).toBe('Overweight');
        expect(bmiCategory(30)).toBe('Obese');
    });
});

describe('navyBodyFat', () => {
    it('calculates body fat for males', () => {
        const percent = navyBodyFat({
            sex: 'male',
            heightCm: 175,
            neckCm: 38,
            waistCm: 85,
        });
        expect(percent).toBeCloseTo(17, 0);
    });

    it('calculates body fat for females using hip', () => {
        const percent = navyBodyFat({
            sex: 'female',
            heightCm: 165,
            neckCm: 32,
            waistCm: 75,
            hipCm: 95,
        });
        expect(percent).toBeCloseTo(27.4, 1);
    });

    it('returns null when waist does not exceed neck', () => {
        expect(
            navyBodyFat({
                sex: 'male',
                heightCm: 175,
                neckCm: 40,
                waistCm: 40,
            }),
        ).toBeNull();
    });

    it('returns null when height is not positive', () => {
        expect(
            navyBodyFat({
                sex: 'male',
                heightCm: 0,
                neckCm: 38,
                waistCm: 85,
            }),
        ).toBeNull();
    });
});

describe('bmiBodyFat', () => {
    it('estimates body fat for males', () => {
        const percent = bmiBodyFat({ bmi: 22.86, age: 30, sex: 'male' });
        expect(percent).toBeCloseTo(18.1, 1);
    });

    it('estimates body fat for females', () => {
        const percent = bmiBodyFat({ bmi: 22.86, age: 30, sex: 'female' });
        expect(percent).toBeCloseTo(28.9, 1);
    });

    it('returns null for invalid inputs', () => {
        expect(bmiBodyFat({ bmi: 0, age: 30, sex: 'male' })).toBeNull();
        expect(bmiBodyFat({ bmi: 22, age: 0, sex: 'male' })).toBeNull();
    });
});

describe('bodyFatCategory', () => {
    it('classifies males by ACE bands', () => {
        expect(bodyFatCategory(3, 'male')).toBe('Essential Fat');
        expect(bodyFatCategory(10, 'male')).toBe('Athletes');
        expect(bodyFatCategory(15, 'male')).toBe('Fitness');
        expect(bodyFatCategory(20, 'male')).toBe('Average');
        expect(bodyFatCategory(30, 'male')).toBe('Obese');
    });

    it('classifies females by ACE bands', () => {
        expect(bodyFatCategory(8, 'female')).toBe('Below Essential');
        expect(bodyFatCategory(12, 'female')).toBe('Essential Fat');
        expect(bodyFatCategory(18, 'female')).toBe('Athletes');
        expect(bodyFatCategory(22, 'female')).toBe('Fitness');
        expect(bodyFatCategory(28, 'female')).toBe('Average');
        expect(bodyFatCategory(35, 'female')).toBe('Obese');
    });
});

describe('mass helpers', () => {
    it('splits weight into fat and lean mass', () => {
        expect(fatMassKg(80, 25)).toBeCloseTo(20, 5);
        expect(leanMassKg(80, 25)).toBeCloseTo(60, 5);
    });
});
