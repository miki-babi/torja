import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { BodyFields } from '@/components/calculator/body-fields';
import { CalculatorShell } from '@/components/calculator/calculator-shell';
import {
    Calculating,
    useCalculating,
} from '@/components/calculator/calculating';
import { MassStats } from '@/components/calculator/mass-stats';
import { MeasurementFields } from '@/components/calculator/measurement-fields';
import { ResultStat } from '@/components/calculator/result-stat';
import { SexToggle } from '@/components/calculator/sex-toggle';
import { UnitToggle } from '@/components/calculator/unit-toggle';
import {
    parseNumber,
    useUnitSystem,
} from '@/components/calculator/use-unit-system';
import { WizardFooter } from '@/components/calculator/wizard-footer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    bmiBodyFat,
    bmiCategory,
    bodyFatCategory,
    calculateBmi,
    navyBodyFat,
    type BodyFatMethod,
    type Sex,
} from '@/lib/body-metrics';

type WizardStep = 'setup' | 'body' | 'measurements' | 'results';

const STEP_TITLES: Record<WizardStep, string> = {
    setup: 'Setup',
    body: 'Body',
    measurements: 'Measurements',
    results: 'Results',
};

export default function Calculator() {
    const u = useUnitSystem();
    const [method, setMethod] = useState<BodyFatMethod>('navy');
    const [sex, setSex] = useState<Sex>('male');
    const [age, setAge] = useState('');
    const [stepIndex, setStepIndex] = useState(0);
    const { isCalculating, messageIndex, start, stop } = useCalculating();

    const steps: WizardStep[] = [
        'setup',
        'body',
        ...(method === 'navy' ? (['measurements'] as WizardStep[]) : []),
        'results',
    ];
    const step = steps[Math.min(stepIndex, steps.length - 1)];

    const bmi = calculateBmi(u.weightKg, u.heightCm);
    const bodyFatPercent =
        method === 'navy'
            ? navyBodyFat({
                  sex,
                  heightCm: u.heightCm,
                  neckCm: u.toCm(u.neck),
                  waistCm: u.toCm(u.waist),
                  hipCm: sex === 'female' ? u.toCm(u.hip) : undefined,
              })
            : bmiBodyFat({
                  bmi: bmi ?? 0,
                  age: parseNumber(age),
                  sex,
              });

    const canContinue = (() => {
        switch (step) {
            case 'body':
                return (
                    u.weightKg > 0 &&
                    u.heightCm > 0 &&
                    (method === 'navy' || parseNumber(age) > 0)
                );
            case 'measurements':
                return bodyFatPercent !== null;
            default:
                return true;
        }
    })();

    const navyInputsInvalid =
        step === 'measurements' &&
        bodyFatPercent === null &&
        (parseNumber(u.waist) > 0 || parseNumber(u.neck) > 0);

    function goNext() {
        if (stepIndex === steps.length - 2) {
            setStepIndex(steps.length - 1);
            start();
            return;
        }
        setStepIndex((i) => Math.min(i + 1, steps.length - 1));
    }

    function reset() {
        setAge('');
        u.resetMeasurements();
        setStepIndex(0);
        stop();
    }

    const cardDescription =
        step === 'setup'
            ? 'Choose your units, method and sex'
            : step === 'body'
              ? 'Enter your weight, height' +
                (method === 'bmi' ? ' and age' : '')
              : step === 'measurements'
                ? 'Measure the required circumferences'
                : 'Your estimated results';

    return (
        <>
            <Head title="Calculator" />
            <CalculatorShell
                headingTitle="Calculator"
                headingDescription="Estimate your BMI and body fat percentage"
                stepCount={steps.length}
                stepIndex={stepIndex}
                stepLabel={STEP_TITLES[step]}
                cardTitle={
                    step === 'results' && isCalculating
                        ? 'Calculating'
                        : STEP_TITLES[step]
                }
                cardDescription={cardDescription}
            >
                {step === 'setup' && (
                    <>
                        <UnitToggle
                            value={u.unit}
                            onChange={u.handleUnitChange}
                        />

                        <div className="grid gap-2">
                            <Label>Body fat method</Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={method}
                                onValueChange={(value) =>
                                    value && setMethod(value as BodyFatMethod)
                                }
                            >
                                <ToggleGroupItem value="navy">
                                    US Navy
                                </ToggleGroupItem>
                                <ToggleGroupItem value="bmi">
                                    BMI-based
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <p className="text-xs text-muted-foreground">
                                {method === 'navy'
                                    ? 'Uses tape measurements for a more accurate estimate.'
                                    : 'Estimates body fat from BMI, age and sex.'}
                            </p>
                        </div>

                        <SexToggle value={sex} onChange={setSex} />
                    </>
                )}

                {step === 'body' && (
                    <BodyFields u={u}>
                        {method === 'bmi' && (
                            <div className="grid gap-2">
                                <Label htmlFor="age">Age (years)</Label>
                                <Input
                                    id="age"
                                    type="number"
                                    inputMode="numeric"
                                    min="0"
                                    value={age}
                                    onChange={(e) => setAge(e.target.value)}
                                    placeholder="30"
                                />
                            </div>
                        )}
                    </BodyFields>
                )}

                {step === 'measurements' && (
                    <MeasurementFields
                        u={u}
                        sex={sex}
                        invalid={navyInputsInvalid}
                    />
                )}

                {step === 'results' && isCalculating && (
                    <Calculating messageIndex={messageIndex} />
                )}

                {step === 'results' && !isCalculating && (
                    <>
                        <ResultStat
                            label="Body Mass Index"
                            value={bmi === null ? '—' : bmi.toFixed(1)}
                            badge={bmi === null ? undefined : bmiCategory(bmi)}
                        />

                        <Separator />

                        <ResultStat
                            label={
                                method === 'navy'
                                    ? 'Body Fat (US Navy)'
                                    : 'Body Fat (BMI estimate)'
                            }
                            value={
                                bodyFatPercent === null
                                    ? '—'
                                    : `${bodyFatPercent.toFixed(1)}%`
                            }
                            badge={
                                bodyFatPercent === null
                                    ? undefined
                                    : bodyFatCategory(bodyFatPercent, sex)
                            }
                        />

                        {bodyFatPercent !== null && u.weightKg > 0 && (
                            <>
                                <Separator />
                                <MassStats
                                    weightKg={u.weightKg}
                                    bodyFatPercent={bodyFatPercent}
                                    toDisplayWeight={u.toDisplayWeight}
                                    weightUnit={u.weightUnit}
                                />
                            </>
                        )}
                    </>
                )}

                {!(step === 'results' && isCalculating) && (
                    <WizardFooter
                        stepIndex={stepIndex}
                        isPenultimate={stepIndex === steps.length - 2}
                        isLast={step === 'results'}
                        canContinue={canContinue}
                        onBack={() => setStepIndex((i) => Math.max(i - 1, 0))}
                        onNext={goNext}
                        onReset={reset}
                    />
                )}
            </CalculatorShell>
        </>
    );
}
