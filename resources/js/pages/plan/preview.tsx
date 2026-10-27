import Heading from '@/components/heading';
import { MobileShell } from '@/components/member/mobile-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { MEAL_TYPE_LABELS } from '@/lib/plan-options';
import { meal } from '@/routes/member';
import { wizard } from '@/routes/plan';
import { show as pro } from '@/routes/pro';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRightLeft,
    CheckCircle2,
    Lock,
} from 'lucide-react';

type TargetWarning = {
    type: string;
    message: string;
};

type Targets = {
    bmr: number;
    tdee: number;
    kcal_target: number;
    protein_g: number;
    warnings: TargetWarning[];
};

type PreviewItem = {
    name: string;
    portion_note: string | null;
    swapped_from: { name: string; reason: string | null } | null;
    excluded: boolean;
};

type SampleDay = {
    plan_name: string;
    day: number;
    meals: { type: string; items: PreviewItem[] }[];
    excluded_count: number;
};

type Props = {
    targets: Targets | null;
    warnings: TargetWarning[];
    sampleDay: SampleDay | null;
    unlocked: boolean;
    price: number;
};

const WARNING_TITLES: Record<string, string> = {
    minor: 'Under 18',
    medical: 'Medical consideration',
    bmi_low: 'Low BMI',
    rapid_change: 'Fast target',
    unrealistic_date: 'Target date',
};

export default function PlanPreview({
    targets,
    warnings,
    sampleDay,
    unlocked,
    price,
}: Props) {
    return (
        <>
            <Head title="Your plan preview" />
            <MobileShell>
                <div className="flex flex-1 flex-col gap-4">
                    <Heading
                        title="Your plan preview"
                        description="A first look at your personalized plan"
                    />

                    {warnings.map((warning, index) => (
                        <Alert key={index} variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>
                                {WARNING_TITLES[warning.type] ?? 'Heads up'}
                            </AlertTitle>
                            <AlertDescription>
                                {warning.message}
                            </AlertDescription>
                        </Alert>
                    ))}

                    {targets === null ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Finish the wizard first</CardTitle>
                                <CardDescription>
                                    We need your age, height, weight and sex to
                                    calculate your targets.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild>
                                    <Link href={wizard()}>
                                        Continue the wizard
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Daily targets
                                </CardTitle>
                                <CardDescription>
                                    Estimates from your answers — adjust as you
                                    go.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <p className="text-3xl font-semibold">
                                            {targets.kcal_target.toLocaleString()}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            kcal target / day
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-3xl font-semibold">
                                            {targets.protein_g} g
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            protein / day
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xl font-medium">
                                            {targets.bmr.toLocaleString()}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            BMR (estimate)
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xl font-medium">
                                            {targets.tdee.toLocaleString()}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            TDEE (estimate)
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {sampleDay === null ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Sample day
                                </CardTitle>
                                <CardDescription>
                                    No meal plan is available for your goal yet.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Sample day from {sampleDay.plan_name}
                                </CardTitle>
                                <CardDescription>
                                    Day {sampleDay.day} — filtered to your diet
                                    and exclusions
                                    {sampleDay.excluded_count > 0 &&
                                        ` (${sampleDay.excluded_count} item${sampleDay.excluded_count === 1 ? '' : 's'} replaced)`}
                                    .
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {sampleDay.meals.map((meal, index) => (
                                    <div key={index}>
                                        {index > 0 && (
                                            <Separator className="mb-4" />
                                        )}
                                        <p className="mb-2 text-sm font-medium">
                                            {MEAL_TYPE_LABELS[meal.type] ??
                                                meal.type}
                                        </p>
                                        <ul className="space-y-1.5 text-sm">
                                            {meal.items.map(
                                                (item, itemIndex) => (
                                                    <li
                                                        key={itemIndex}
                                                        className="flex flex-wrap items-center gap-2"
                                                    >
                                                        <span>
                                                            {item.name}
                                                            {item.portion_note &&
                                                                ` — ${item.portion_note}`}
                                                        </span>
                                                        {item.swapped_from && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1 font-normal"
                                                            >
                                                                <ArrowRightLeft className="size-3" />
                                                                swapped from{' '}
                                                                {
                                                                    item
                                                                        .swapped_from
                                                                        .name
                                                                }
                                                                {item
                                                                    .swapped_from
                                                                    .reason &&
                                                                    `: ${item.swapped_from.reason}`}
                                                            </Badge>
                                                        )}
                                                        {item.excluded && (
                                                            <Badge
                                                                variant="destructive"
                                                                className="font-normal"
                                                            >
                                                                excluded — no
                                                                safe swap found
                                                            </Badge>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}

                    {unlocked ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2 className="size-4 text-green-600" />
                                    Plan unlocked
                                </CardTitle>
                                <CardDescription>
                                    Your payment went through — the full plan is
                                    yours.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    All 7 days of your meal plan are unlocked —
                                    find them on the Nutrition tab, day by day.
                                </p>
                                <Button
                                    asChild
                                    className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                >
                                    <Link href={meal()}>
                                        Open your full plan
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Lock className="size-4" />
                                    Unlock your full plan
                                </CardTitle>
                                <CardDescription>
                                    The complete 7-day plan with portions,
                                    grocery lists and fasting-day adaptations.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    One-time payment of {price.toLocaleString()}{' '}
                                    ETB — card, bank transfer and mobile money
                                    accepted.
                                </p>
                                <Button
                                    asChild
                                    className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                >
                                    <Link href={pro()}>Go Pro</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </MobileShell>
        </>
    );
}
