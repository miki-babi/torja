import { update } from '@/actions/App/Http/Controllers/Plan/NutritionWizardController';
import { MobileShell } from '@/components/member/mobile-shell';
import Heading from '@/components/heading';
import Step02Goal from '@/components/plan/steps/Step02Goal';
import Step03Body from '@/components/plan/steps/Step03Body';
import Step04Activity from '@/components/plan/steps/Step04Activity';
import Step05Pace from '@/components/plan/steps/Step05Pace';
import Step06Diet from '@/components/plan/steps/Step06Diet';
import Step07LikedFoods from '@/components/plan/steps/Step07LikedFoods';
import Step08Avoidances from '@/components/plan/steps/Step08Avoidances';
import Step09Habits from '@/components/plan/steps/Step09Habits';
import Step10Schedule from '@/components/plan/steps/Step10Schedule';
import Step11Budget from '@/components/plan/steps/Step11Budget';
import Step12Cooking from '@/components/plan/steps/Step12Cooking';
import Step13Obstacles from '@/components/plan/steps/Step13Obstacles';
import { WizardShell } from '@/components/plan/wizard-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    SECTION_META,
    STEP_SECTIONS,
    TOTAL_STEPS,
    mergeAnswers,
    toSectionPayload,
    type PickerProps,
    type SectionKey,
    type WizardAnswers,
} from '@/lib/plan-options';
import { dashboard, home, login, register } from '@/routes';
import { review } from '@/routes/plan';
import type { Auth } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    currentStep: number;
    status: string;
    answers: Partial<Record<SectionKey, object>>;
    /** Deferred prop — undefined until loaded. */
    picker?: PickerProps;
};

const INTRO_BULLETS = [
    'Your goal, body and activity level',
    'The foods you love, and the ones to avoid',
    'Your schedule, budget and cooking setup',
];

export default function PlanWizard({
    currentStep,
    status,
    answers: savedAnswers,
    picker,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [step, setStep] = useState(() =>
        Math.min(Math.max(currentStep || 1, 1), TOTAL_STEPS),
    );
    const [answers, setAnswers] = useState<WizardAnswers>(() =>
        mergeAnswers(savedAnswers),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const section = STEP_SECTIONS[step];

    function patchSection<K extends SectionKey>(
        key: K,
        data: Partial<WizardAnswers[K]>,
    ) {
        setAnswers((previous) => ({
            ...previous,
            [key]: { ...previous[key], ...data },
        }));
    }

    function goBack() {
        setErrors({});
        setStep((current) => Math.max(current - 1, 1));
    }

    function goNext() {
        if (!section) {
            setStep(2);
            return;
        }

        setErrors({});
        setProcessing(true);

        router.patch(update.url(section), toSectionPayload(answers[section]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (step === TOTAL_STEPS) {
                    router.visit(review.url());
                    return;
                }
                setStep((current) => Math.min(current + 1, TOTAL_STEPS));
            },
            onError: (validationErrors) => {
                setErrors(validationErrors);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    }

    return (
        <>
            <Head title="Personalize your plan" />
            <MobileShell>
                <div className="flex w-full flex-col items-center">
                    <header className="mb-6 flex w-full max-w-5xl items-center justify-between text-sm">
                        <Link href={home()} className="font-medium">
                            Personalize my plan
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
                        {step === 1 && (
                            <>
                                <Heading
                                    title="Personalize your nutrition plan"
                                    description="Answer a few questions and we'll build a plan around Ethiopian food you actually like."
                                />
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            What we'll ask about
                                        </CardTitle>
                                        <CardDescription>
                                            Takes about 3 minutes — you can stop
                                            and resume anytime.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="list-disc space-y-2 pl-5 text-sm">
                                            {INTRO_BULLETS.map((bullet) => (
                                                <li key={bullet}>{bullet}</li>
                                            ))}
                                        </ul>
                                        <p className="mt-4 text-xs text-muted-foreground">
                                            Your answers are saved to this
                                            session until you create an account.
                                        </p>
                                        {status === 'completed' && (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                You've finished this wizard —
                                                you can still review or change
                                                answers below.
                                            </p>
                                        )}
                                    </CardContent>
                                    <CardFooter className="justify-end">
                                        <Button
                                            onClick={() => setStep(2)}
                                            className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                        >
                                            {currentStep > 1
                                                ? 'Resume'
                                                : 'Get started'}
                                        </Button>
                                    </CardFooter>
                                </Card>
                            </>
                        )}

                        {section && (
                            <WizardShell
                                step={step}
                                totalSteps={TOTAL_STEPS}
                                title={SECTION_META[section].title}
                                description={SECTION_META[section].description}
                                onBack={goBack}
                                onNext={goNext}
                                nextLabel={
                                    step === TOTAL_STEPS
                                        ? 'Finish & review'
                                        : 'Continue'
                                }
                                processing={processing}
                            >
                                {section === 'goal' && (
                                    <Step02Goal
                                        value={answers.goal}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('goal', data)
                                        }
                                    />
                                )}
                                {section === 'body' && (
                                    <Step03Body
                                        value={answers.body}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('body', data)
                                        }
                                    />
                                )}
                                {section === 'activity' && (
                                    <Step04Activity
                                        value={answers.activity}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('activity', data)
                                        }
                                    />
                                )}
                                {section === 'pace' && (
                                    <Step05Pace
                                        value={answers.pace}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('pace', data)
                                        }
                                    />
                                )}
                                {section === 'diet' && (
                                    <Step06Diet
                                        value={answers.diet}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('diet', data)
                                        }
                                    />
                                )}
                                {section === 'liked_foods' && (
                                    <Step07LikedFoods
                                        value={answers.liked_foods}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('liked_foods', data)
                                        }
                                    />
                                )}
                                {section === 'avoidances' && (
                                    <Step08Avoidances
                                        value={answers.avoidances}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('avoidances', data)
                                        }
                                    />
                                )}
                                {section === 'habits' && (
                                    <Step09Habits
                                        value={answers.habits}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('habits', data)
                                        }
                                    />
                                )}
                                {section === 'schedule' && (
                                    <Step10Schedule
                                        value={answers.schedule}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('schedule', data)
                                        }
                                    />
                                )}
                                {section === 'budget' && (
                                    <Step11Budget
                                        value={answers.budget}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('budget', data)
                                        }
                                    />
                                )}
                                {section === 'cooking' && (
                                    <Step12Cooking
                                        value={answers.cooking}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('cooking', data)
                                        }
                                    />
                                )}
                                {section === 'obstacles' && (
                                    <Step13Obstacles
                                        value={answers.obstacles}
                                        errors={errors}
                                        picker={picker}
                                        onChange={(data) =>
                                            patchSection('obstacles', data)
                                        }
                                    />
                                )}
                            </WizardShell>
                        )}
                    </main>
                </div>
            </MobileShell>
        </>
    );
}
