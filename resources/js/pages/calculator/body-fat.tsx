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
import { Separator } from '@/components/ui/separator';
import TextLink from '@/components/text-link';
import {
    bmiCategory,
    bodyFatCategory,
    calculateBmi,
    navyBodyFat,
    type Sex,
} from '@/lib/body-metrics';
import { bmi } from '@/routes/calculator';

type WizardStep = 'setup' | 'body' | 'measurements' | 'results';

const STEP_TITLES: Record<WizardStep, string> = {
    setup: 'Setup',
    body: 'Body',
    measurements: 'Measurements',
    results: 'Results',
};

const steps: WizardStep[] = ['setup', 'body', 'measurements', 'results'];

export default function BodyFatCalculator() {
    const u = useUnitSystem();
    const [sex, setSex] = useState<Sex>('male');
    const [stepIndex, setStepIndex] = useState(0);
    const { isCalculating, messageIndex, start, stop } = useCalculating();

    const step = steps[Math.min(stepIndex, steps.length - 1)];

    const bmiValue = calculateBmi(u.weightKg, u.heightCm);
    const bodyFatPercent = navyBodyFat({
        sex,
        heightCm: u.heightCm,
        neckCm: u.toCm(u.neck),
        waistCm: u.toCm(u.waist),
        hipCm: sex === 'female' ? u.toCm(u.hip) : undefined,
    });

    const canContinue = (() => {
        switch (step) {
            case 'body':
                return u.weightKg > 0 && u.heightCm > 0;
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
        u.resetMeasurements();
        setStepIndex(0);
        stop();
    }

    const cardDescription =
        step === 'setup'
            ? 'Choose your units and sex'
            : step === 'body'
              ? 'Enter your weight and height'
              : step === 'measurements'
                ? 'Measure the required circumferences'
                : 'Your estimated results';

    return (
        <>
            <Head title="Body Fat Calculator" />
            <CalculatorShell
                headingTitle="Body Fat Calculator"
                headingDescription="Estimate your body fat percentage with the US Navy method"
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
                        <SexToggle value={sex} onChange={setSex} />
                    </>
                )}

                {step === 'body' && <BodyFields u={u} />}

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
                            label="Body Fat (US Navy)"
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

                        <Separator />

                        <ResultStat
                            label="Body Mass Index"
                            value={
                                bmiValue === null ? '—' : bmiValue.toFixed(1)
                            }
                            badge={
                                bmiValue === null
                                    ? undefined
                                    : bmiCategory(bmiValue)
                            }
                        />

                        <p className="text-sm text-muted-foreground">
                            Just want your BMI?{' '}
                            <TextLink href={bmi()}>
                                Try the BMI calculator
                            </TextLink>
                            .
                        </p>
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
