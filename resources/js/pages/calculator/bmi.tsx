import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { BodyFields } from '@/components/calculator/body-fields';
import { CalculatorShell } from '@/components/calculator/calculator-shell';
import {
    Calculating,
    useCalculating,
} from '@/components/calculator/calculating';
import { ResultStat } from '@/components/calculator/result-stat';
import { UnitToggle } from '@/components/calculator/unit-toggle';
import { useUnitSystem } from '@/components/calculator/use-unit-system';
import { WizardFooter } from '@/components/calculator/wizard-footer';
import TextLink from '@/components/text-link';
import { bmiCategory, calculateBmi } from '@/lib/body-metrics';
import { bodyFat } from '@/routes/calculator';

type WizardStep = 'setup' | 'body' | 'results';

const STEP_TITLES: Record<WizardStep, string> = {
    setup: 'Setup',
    body: 'Body',
    results: 'Results',
};

const steps: WizardStep[] = ['setup', 'body', 'results'];

export default function BmiCalculator() {
    const u = useUnitSystem();
    const [stepIndex, setStepIndex] = useState(0);
    const { isCalculating, messageIndex, start, stop } = useCalculating();

    const step = steps[Math.min(stepIndex, steps.length - 1)];
    const bmi = calculateBmi(u.weightKg, u.heightCm);

    const canContinue =
        step === 'body' ? u.weightKg > 0 && u.heightCm > 0 : true;

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
            ? 'Choose your units'
            : step === 'body'
              ? 'Enter your weight and height'
              : 'Your estimated results';

    return (
        <>
            <Head title="BMI Calculator" />
            <CalculatorShell
                headingTitle="BMI Calculator"
                headingDescription="Estimate your Body Mass Index"
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
                    <UnitToggle value={u.unit} onChange={u.handleUnitChange} />
                )}

                {step === 'body' && <BodyFields u={u} />}

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
                        <p className="text-sm text-muted-foreground">
                            Want to estimate body fat instead?{' '}
                            <TextLink href={bodyFat()}>
                                Try the body fat calculator
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
