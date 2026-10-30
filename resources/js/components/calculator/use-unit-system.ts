import { useState } from 'react';
import {
    cmToFeetInches,
    cmToInches,
    feetInchesToCm,
    inchesToCm,
    kgToPounds,
    poundsToKg,
    type UnitSystem,
} from '@/lib/body-metrics';

export function parseNumber(value: string): number {
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

function formatForInput(value: number): string {
    return value > 0 ? String(Math.round(value * 10) / 10) : '';
}

export function useUnitSystem() {
    const [unit, setUnit] = useState<UnitSystem>('metric');
    const [weight, setWeight] = useState('');
    const [height, setHeight] = useState('');
    const [heightFt, setHeightFt] = useState('');
    const [heightIn, setHeightIn] = useState('');
    const [neck, setNeck] = useState('');
    const [waist, setWaist] = useState('');
    const [hip, setHip] = useState('');

    const isMetric = unit === 'metric';
    const lengthUnit = isMetric ? 'cm' : 'in';
    const weightUnit = isMetric ? 'kg' : 'lb';

    function handleUnitChange(next: string) {
        if (!next || next === unit) {
            return;
        }
        const toImperial = next === 'imperial';
        const convertLength = (value: string) => {
            const n = parseNumber(value);
            if (n <= 0) {
                return '';
            }
            return formatForInput(toImperial ? cmToInches(n) : inchesToCm(n));
        };
        const weightValue = parseNumber(weight);
        setWeight(
            weightValue > 0
                ? formatForInput(
                      toImperial
                          ? kgToPounds(weightValue)
                          : poundsToKg(weightValue),
                  )
                : '',
        );
        setNeck(convertLength(neck));
        setWaist(convertLength(waist));
        setHip(convertLength(hip));
        if (toImperial) {
            const heightValue = parseNumber(height);
            if (heightValue > 0) {
                const { feet, inches } = cmToFeetInches(heightValue);
                setHeightFt(String(feet));
                setHeightIn(formatForInput(inches));
            } else {
                setHeightFt('');
                setHeightIn('');
            }
        } else {
            const cm = feetInchesToCm(
                parseNumber(heightFt),
                parseNumber(heightIn),
            );
            setHeight(cm > 0 ? formatForInput(cm) : '');
        }
        setUnit(next as UnitSystem);
    }

    const weightKg = isMetric
        ? parseNumber(weight)
        : poundsToKg(parseNumber(weight));
    const heightCm = isMetric
        ? parseNumber(height)
        : feetInchesToCm(parseNumber(heightFt), parseNumber(heightIn));
    const toCm = (value: string) =>
        isMetric ? parseNumber(value) : inchesToCm(parseNumber(value));
    const toDisplayWeight = (kg: number) => (isMetric ? kg : kgToPounds(kg));

    function resetMeasurements() {
        setWeight('');
        setHeight('');
        setHeightFt('');
        setHeightIn('');
        setNeck('');
        setWaist('');
        setHip('');
    }

    return {
        unit,
        isMetric,
        lengthUnit,
        weightUnit,
        handleUnitChange,
        weight,
        setWeight,
        height,
        setHeight,
        heightFt,
        setHeightFt,
        heightIn,
        setHeightIn,
        neck,
        setNeck,
        waist,
        setWaist,
        hip,
        setHip,
        weightKg,
        heightCm,
        toCm,
        toDisplayWeight,
        resetMeasurements,
    };
}
