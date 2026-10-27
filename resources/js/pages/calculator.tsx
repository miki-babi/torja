import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { MobileShell } from '@/components/member/mobile-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    bmiBodyFat,
    bmiCategory,
    bodyFatCategory,
    calculateBmi,
    cmToFeetInches,
    cmToInches,
    fatMassKg,
    feetInchesToCm,
    inchesToCm,
    kgToPounds,
    leanMassKg,
    navyBodyFat,
    poundsToKg,
    type BodyFatMethod,
    type Sex,
    type UnitSystem,
} from '@/lib/body-metrics';
import { cn } from '@/lib/utils';
import { dashboard, home, login, register } from '@/routes';
import type { Auth } from '@/types';

function parseNumber(value: string): number {
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

function formatForInput(value: number): string {
    return value > 0 ? String(Math.round(value * 10) / 10) : '';
}

type WizardStep = 'setup' | 'body' | 'measurements' | 'results';

const STEP_TITLES: Record<WizardStep, string> = {
    setup: 'Setup',
    body: 'Body',
    measurements: 'Measurements',
    results: 'Results',
};

const CALC_MESSAGES = [
    'Checking your inputs…',
    'Applying the formulas…',
    'Finishing up…',
];

export default function Calculator() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [unit, setUnit] = useState<UnitSystem>('metric');
    const [method, setMethod] = useState<BodyFatMethod>('navy');
    const [sex, setSex] = useState<Sex>('male');
    const [age, setAge] = useState('');
    const [weight, setWeight] = useState('');
    const [height, setHeight] = useState('');
    const [heightFt, setHeightFt] = useState('');
    const [heightIn, setHeightIn] = useState('');
    const [neck, setNeck] = useState('');
    const [waist, setWaist] = useState('');
    const [hip, setHip] = useState('');
    const [stepIndex, setStepIndex] = useState(0);
    const [isCalculating, setIsCalculating] = useState(false);
    const [calcMessageIndex, setCalcMessageIndex] = useState(0);

    useEffect(() => {
        if (!isCalculating) {
            return;
        }
        setCalcMessageIndex(0);
        const messageTimer = setInterval(() => {
            setCalcMessageIndex((i) =>
                Math.min(i + 1, CALC_MESSAGES.length - 1),
            );
        }, 700);
        const doneTimer = setTimeout(() => {
            setIsCalculating(false);
        }, 2200);
        return () => {
            clearInterval(messageTimer);
            clearTimeout(doneTimer);
        };
    }, [isCalculating]);

    const steps: WizardStep[] = [
        'setup',
        'body',
        ...(method === 'navy' ? (['measurements'] as WizardStep[]) : []),
        'results',
    ];
    const step = steps[Math.min(stepIndex, steps.length - 1)];
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

    const bmi = calculateBmi(weightKg, heightCm);
    const bodyFatPercent =
        method === 'navy'
            ? navyBodyFat({
                  sex,
                  heightCm,
                  neckCm: toCm(neck),
                  waistCm: toCm(waist),
                  hipCm: sex === 'female' ? toCm(hip) : undefined,
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
                    weightKg > 0 &&
                    heightCm > 0 &&
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
        (parseNumber(waist) > 0 || parseNumber(neck) > 0);

    const toDisplayWeight = (kg: number) => (isMetric ? kg : kgToPounds(kg));

    function goNext() {
        if (stepIndex === steps.length - 2) {
            setStepIndex(steps.length - 1);
            setIsCalculating(true);
            return;
        }
        setStepIndex((i) => Math.min(i + 1, steps.length - 1));
    }

    function goBack() {
        setStepIndex((i) => Math.max(i - 1, 0));
    }

    function reset() {
        setAge('');
        setWeight('');
        setHeight('');
        setHeightFt('');
        setHeightIn('');
        setNeck('');
        setWaist('');
        setHip('');
        setStepIndex(0);
        setIsCalculating(false);
    }

    return (
        <>
            <Head title="Calculator" />
            <MobileShell>
                <div className="flex w-full flex-col items-center">
                    <header className="mb-6 flex w-full items-center justify-between text-sm">
                        <Link href={home()} className="font-medium">
                            Calculator
                        </Link>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-block rounded-sm border border-border px-5 py-1.5 text-sm leading-normal hover:border-foreground/40"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal hover:border-border"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-block rounded-sm border border-border px-5 py-1.5 text-sm leading-normal hover:border-foreground/40"
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>
                    <main className="flex w-full flex-1 flex-col gap-4">
                        <Heading
                            title="Calculator"
                            description="Estimate your BMI and body fat percentage"
                        />

                        <div className="flex gap-2">
                            {steps.map((s, i) => (
                                <span
                                    key={s}
                                    className={cn(
                                        'h-1.5 flex-1 rounded-full',
                                        i <= stepIndex
                                            ? 'bg-foreground'
                                            : 'bg-muted',
                                    )}
                                />
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Step {stepIndex + 1} of {steps.length} —{' '}
                            {STEP_TITLES[step]}
                        </p>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {step === 'results' && isCalculating
                                        ? 'Calculating'
                                        : STEP_TITLES[step]}
                                </CardTitle>
                                <CardDescription>
                                    {step === 'setup' &&
                                        'Choose your units, method and sex'}
                                    {step === 'body' &&
                                        'Enter your weight, height' +
                                            (method === 'bmi'
                                                ? ' and age'
                                                : '')}
                                    {step === 'measurements' &&
                                        'Measure the required circumferences'}
                                    {step === 'results' &&
                                        'Your estimated results'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {step === 'setup' && (
                                    <>
                                        <div className="grid gap-2">
                                            <Label>Units</Label>
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                value={unit}
                                                onValueChange={handleUnitChange}
                                            >
                                                <ToggleGroupItem value="metric">
                                                    Metric
                                                </ToggleGroupItem>
                                                <ToggleGroupItem value="imperial">
                                                    Imperial
                                                </ToggleGroupItem>
                                            </ToggleGroup>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Body fat method</Label>
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                value={method}
                                                onValueChange={(value) =>
                                                    value &&
                                                    setMethod(
                                                        value as BodyFatMethod,
                                                    )
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

                                        <div className="grid gap-2">
                                            <Label>Sex</Label>
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                value={sex}
                                                onValueChange={(value) =>
                                                    value &&
                                                    setSex(value as Sex)
                                                }
                                            >
                                                <ToggleGroupItem value="male">
                                                    Male
                                                </ToggleGroupItem>
                                                <ToggleGroupItem value="female">
                                                    Female
                                                </ToggleGroupItem>
                                            </ToggleGroup>
                                        </div>
                                    </>
                                )}

                                {step === 'body' && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="weight">
                                                Weight ({weightUnit})
                                            </Label>
                                            <Input
                                                id="weight"
                                                type="number"
                                                inputMode="decimal"
                                                min="0"
                                                step="any"
                                                value={weight}
                                                onChange={(e) =>
                                                    setWeight(e.target.value)
                                                }
                                                placeholder={
                                                    isMetric ? '70' : '154'
                                                }
                                            />
                                        </div>

                                        {isMetric ? (
                                            <div className="grid gap-2">
                                                <Label htmlFor="height">
                                                    Height (cm)
                                                </Label>
                                                <Input
                                                    id="height"
                                                    type="number"
                                                    inputMode="decimal"
                                                    min="0"
                                                    step="any"
                                                    value={height}
                                                    onChange={(e) =>
                                                        setHeight(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="175"
                                                />
                                            </div>
                                        ) : (
                                            <div className="grid gap-2">
                                                <Label>Height</Label>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="grid gap-1">
                                                        <Input
                                                            id="height-ft"
                                                            type="number"
                                                            inputMode="decimal"
                                                            min="0"
                                                            step="any"
                                                            value={heightFt}
                                                            onChange={(e) =>
                                                                setHeightFt(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="5"
                                                        />
                                                        <Label
                                                            htmlFor="height-ft"
                                                            className="text-xs text-muted-foreground"
                                                        >
                                                            ft
                                                        </Label>
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Input
                                                            id="height-in"
                                                            type="number"
                                                            inputMode="decimal"
                                                            min="0"
                                                            step="any"
                                                            value={heightIn}
                                                            onChange={(e) =>
                                                                setHeightIn(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="9"
                                                        />
                                                        <Label
                                                            htmlFor="height-in"
                                                            className="text-xs text-muted-foreground"
                                                        >
                                                            in
                                                        </Label>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {method === 'bmi' && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="age">
                                                    Age (years)
                                                </Label>
                                                <Input
                                                    id="age"
                                                    type="number"
                                                    inputMode="numeric"
                                                    min="0"
                                                    value={age}
                                                    onChange={(e) =>
                                                        setAge(e.target.value)
                                                    }
                                                    placeholder="30"
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}

                                {step === 'measurements' && (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="neck">
                                                    Neck ({lengthUnit})
                                                </Label>
                                                <Input
                                                    id="neck"
                                                    type="number"
                                                    inputMode="decimal"
                                                    min="0"
                                                    step="any"
                                                    value={neck}
                                                    onChange={(e) =>
                                                        setNeck(e.target.value)
                                                    }
                                                    placeholder={
                                                        isMetric ? '38' : '15'
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="waist">
                                                    Waist ({lengthUnit})
                                                </Label>
                                                <Input
                                                    id="waist"
                                                    type="number"
                                                    inputMode="decimal"
                                                    min="0"
                                                    step="any"
                                                    value={waist}
                                                    onChange={(e) =>
                                                        setWaist(e.target.value)
                                                    }
                                                    placeholder={
                                                        isMetric ? '85' : '33'
                                                    }
                                                />
                                            </div>
                                            {sex === 'female' && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="hip">
                                                        Hip ({lengthUnit})
                                                    </Label>
                                                    <Input
                                                        id="hip"
                                                        type="number"
                                                        inputMode="decimal"
                                                        min="0"
                                                        step="any"
                                                        value={hip}
                                                        onChange={(e) =>
                                                            setHip(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={
                                                            isMetric
                                                                ? '95'
                                                                : '37'
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </div>
                                        {navyInputsInvalid && (
                                            <p className="text-sm text-muted-foreground">
                                                {sex === 'male'
                                                    ? 'Waist must be larger than neck.'
                                                    : 'Waist plus hip must be larger than neck.'}
                                            </p>
                                        )}
                                    </>
                                )}

                                {step === 'results' && isCalculating && (
                                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                                        <Spinner className="size-8 text-muted-foreground" />
                                        <div>
                                            <p className="font-medium">
                                                Calculating your results
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    CALC_MESSAGES[
                                                        calcMessageIndex
                                                    ]
                                                }
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {step === 'results' && !isCalculating && (
                                    <>
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Body Mass Index
                                            </p>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                <span className="text-3xl font-semibold tracking-tight">
                                                    {bmi === null
                                                        ? '—'
                                                        : bmi.toFixed(1)}
                                                </span>
                                                {bmi !== null && (
                                                    <Badge variant="secondary">
                                                        {bmiCategory(bmi)}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>

                                        <Separator />

                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Body Fat{' '}
                                                {method === 'navy'
                                                    ? '(US Navy)'
                                                    : '(BMI estimate)'}
                                            </p>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                <span className="text-3xl font-semibold tracking-tight">
                                                    {bodyFatPercent === null
                                                        ? '—'
                                                        : `${bodyFatPercent.toFixed(1)}%`}
                                                </span>
                                                {bodyFatPercent !== null && (
                                                    <Badge variant="secondary">
                                                        {bodyFatCategory(
                                                            bodyFatPercent,
                                                            sex,
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>

                                        {bodyFatPercent !== null &&
                                            weightKg > 0 && (
                                                <>
                                                    <Separator />
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div>
                                                            <p className="text-sm text-muted-foreground">
                                                                Fat mass
                                                            </p>
                                                            <p className="mt-1 text-xl font-medium">
                                                                {toDisplayWeight(
                                                                    fatMassKg(
                                                                        weightKg,
                                                                        bodyFatPercent,
                                                                    ),
                                                                ).toFixed(
                                                                    1,
                                                                )}{' '}
                                                                {weightUnit}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <p className="text-sm text-muted-foreground">
                                                                Lean mass
                                                            </p>
                                                            <p className="mt-1 text-xl font-medium">
                                                                {toDisplayWeight(
                                                                    leanMassKg(
                                                                        weightKg,
                                                                        bodyFatPercent,
                                                                    ),
                                                                ).toFixed(
                                                                    1,
                                                                )}{' '}
                                                                {weightUnit}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                    </>
                                )}

                                {!(step === 'results' && isCalculating) && (
                                    <div className="flex items-center justify-between">
                                        <Button
                                            variant="ghost"
                                            onClick={goBack}
                                            disabled={stepIndex === 0}
                                        >
                                            Back
                                        </Button>
                                        {step === 'results' ? (
                                            <Button onClick={reset}>
                                                Start over
                                            </Button>
                                        ) : (
                                            <Button
                                                onClick={goNext}
                                                disabled={!canContinue}
                                            >
                                                {stepIndex === steps.length - 2
                                                    ? 'See results'
                                                    : 'Next'}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </main>
                </div>
            </MobileShell>
        </>
    );
}
