export type Sex = 'male' | 'female';
export type UnitSystem = 'metric' | 'imperial';
export type BodyFatMethod = 'navy' | 'bmi';

export type BmiCategory = 'Underweight' | 'Normal' | 'Overweight' | 'Obese';

export type BodyFatCategory =
    | 'Below Essential'
    | 'Essential Fat'
    | 'Athletes'
    | 'Fitness'
    | 'Average'
    | 'Obese';

const CM_PER_INCH = 2.54;
const KG_PER_POUND = 0.45359237;

export function inchesToCm(inches: number): number {
    return inches * CM_PER_INCH;
}

export function cmToInches(cm: number): number {
    return cm / CM_PER_INCH;
}

export function feetInchesToCm(feet: number, inches: number): number {
    return inchesToCm(feet * 12 + inches);
}

export function cmToFeetInches(cm: number): { feet: number; inches: number } {
    const totalInches = cmToInches(cm);
    const feet = Math.floor(totalInches / 12);
    return { feet, inches: totalInches - feet * 12 };
}

export function poundsToKg(pounds: number): number {
    return pounds * KG_PER_POUND;
}

export function kgToPounds(kg: number): number {
    return kg / KG_PER_POUND;
}

export function calculateBmi(
    weightKg: number,
    heightCm: number,
): number | null {
    if (weightKg <= 0 || heightCm <= 0) {
        return null;
    }

    const heightM = heightCm / 100;
    return weightKg / (heightM * heightM);
}

export function bmiCategory(bmi: number): BmiCategory {
    if (bmi < 18.5) {
        return 'Underweight';
    }
    if (bmi < 25) {
        return 'Normal';
    }
    if (bmi < 30) {
        return 'Overweight';
    }
    return 'Obese';
}

export function navyBodyFat(input: {
    sex: Sex;
    heightCm: number;
    neckCm: number;
    waistCm: number;
    hipCm?: number;
}): number | null {
    const { sex, heightCm, neckCm, waistCm, hipCm } = input;
    const circumference =
        sex === 'male' ? waistCm - neckCm : waistCm + (hipCm ?? 0) - neckCm;

    if (heightCm <= 0 || circumference <= 0) {
        return null;
    }

    const density =
        sex === 'male'
            ? 1.0324 -
              0.19077 * Math.log10(circumference) +
              0.15456 * Math.log10(heightCm)
            : 1.29579 -
              0.35004 * Math.log10(circumference) +
              0.221 * Math.log10(heightCm);
    const percent = 495 / density - 450;

    if (!Number.isFinite(percent)) {
        return null;
    }
    return Math.min(100, Math.max(0, percent));
}

export function bmiBodyFat(input: {
    bmi: number;
    age: number;
    sex: Sex;
}): number | null {
    const { bmi, age, sex } = input;

    if (bmi <= 0 || age <= 0) {
        return null;
    }

    const percent =
        1.2 * bmi + 0.23 * age - 10.8 * (sex === 'male' ? 1 : 0) - 5.4;
    return Math.min(100, Math.max(0, percent));
}

export function bodyFatCategory(percent: number, sex: Sex): BodyFatCategory {
    if (sex === 'male') {
        if (percent < 2) {
            return 'Below Essential';
        }
        if (percent <= 5) {
            return 'Essential Fat';
        }
        if (percent <= 13) {
            return 'Athletes';
        }
        if (percent <= 17) {
            return 'Fitness';
        }
        if (percent <= 24) {
            return 'Average';
        }
        return 'Obese';
    }

    if (percent < 10) {
        return 'Below Essential';
    }
    if (percent <= 13) {
        return 'Essential Fat';
    }
    if (percent <= 20) {
        return 'Athletes';
    }
    if (percent <= 24) {
        return 'Fitness';
    }
    if (percent <= 31) {
        return 'Average';
    }
    return 'Obese';
}

export function fatMassKg(weightKg: number, bodyFatPercent: number): number {
    return weightKg * (bodyFatPercent / 100);
}

export function leanMassKg(weightKg: number, bodyFatPercent: number): number {
    return weightKg - fatMassKg(weightKg, bodyFatPercent);
}
