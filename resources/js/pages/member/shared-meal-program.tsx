import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    ListChecks,
    Lock,
    Share2,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { mealTypeLabel } from '@/components/member/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTranslation } from '@/lib/i18n';
import { login } from '@/routes';

type SharedMealItem = {
    name: string;
    image_url: string | null;
    portion_note: string | null;
    macros: {
        kcal_per_100g: number | null;
        protein_g: number | null;
        carbs_g: number | null;
        fat_g: number | null;
    } | null;
};

type SharedProgramMeal = {
    type: string;
    items: SharedMealItem[];
};

type SharedProgramDay = {
    day: number;
    meals: SharedProgramMeal[];
};

type SharedMealProgram = {
    name: string;
    owner: string;
    description: string | null;
    image_url: string | null;
    highlights: string[];
    days: SharedProgramDay[];
};

export default function MemberSharedMealProgram({
    program,
    isOwner,
    isPro,
    alreadyClaimed,
    saveUrl,
    proUrl,
    homeUrl,
    editUrl,
    claimedPlanUrl,
}: {
    program: SharedMealProgram;
    isOwner: boolean;
    isPro: boolean;
    alreadyClaimed: boolean;
    saveUrl: string;
    proUrl: string;
    homeUrl: string;
    editUrl: string | null;
    claimedPlanUrl: string | null;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const [saving, setSaving] = useState(false);
    const [claimed, setClaimed] = useState(alreadyClaimed);
    const [planUrl, setPlanUrl] = useState(claimedPlanUrl);
    const isGuest = auth.user === null;

    async function save() {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            const data = await memberApi<{
                claimed: boolean;
                plan_url: string | null;
            }>(saveUrl, 'POST');
            setClaimed(true);
            setPlanUrl(data.plan_url);
            toast.success(t('shared_meal_program.saved_toast'));
        } catch {
            toast.error(t('shared_meal_program.save_failed_toast'));
        } finally {
            setSaving(false);
        }
    }

    const mealCount = program.days.reduce(
        (total, day) => total + day.meals.length,
        0,
    );
    const itemCount = program.days.reduce(
        (total, day) =>
            total + day.meals.reduce((sum, meal) => sum + meal.items.length, 0),
        0,
    );
    const mealTypes = [
        ...new Set(
            program.days.flatMap((day) => day.meals.map((meal) => meal.type)),
        ),
    ];

    return (
        <MobileShell hideNav>
            <Head title={program.name} />

            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl">
                {program.image_url !== null ? (
                    <img
                        src={program.image_url}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center bg-lime-200/60 dark:bg-lime-900/40">
                        <UtensilsCrossed className="size-16 text-lime-600/40 dark:text-lime-400/40" />
                    </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/10" />
                <div className="absolute inset-x-3 top-3 flex items-start justify-between">
                    <span className="flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1 text-xs text-white backdrop-blur">
                        <Share2 className="size-3.5" />
                        {t('shared_program.shared')}
                    </span>
                    <Badge className="bg-lime-400 text-lime-950">
                        {t('shared_meal_program.pro_badge')}
                    </Badge>
                </div>
                <div className="absolute inset-x-0 bottom-0 p-4">
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {program.name}
                    </h1>
                    <p className="mt-1 text-sm text-white/80">
                        {t('shared_program.shared_by', {
                            owner: program.owner,
                        })}{' '}
                        ·{' '}
                        {t(
                            program.days.length === 1
                                ? 'shared_meal_program.day_count'
                                : 'shared_meal_program.days_count',
                            { count: program.days.length },
                        )}{' '}
                        ·{' '}
                        {t(
                            mealCount === 1
                                ? 'shared_meal_program.meal_count'
                                : 'shared_meal_program.meals_count',
                            { count: mealCount },
                        )}
                    </p>
                </div>
            </div>

            <Card className="mt-5">
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('shared_program.whats_included')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-lg border p-3">
                            <CalendarDays className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-lg leading-none font-semibold">
                                {program.days.length}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_meal_program.guided_days')}
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <ListChecks className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-lg leading-none font-semibold">
                                {itemCount}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_meal_program.foods_with_macros')}
                            </p>
                        </div>
                    </div>

                    {mealTypes.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {mealTypes.map((type) => (
                                <Badge key={type} variant="secondary">
                                    {mealTypeLabel(type)}
                                </Badge>
                            ))}
                        </div>
                    )}

                    {program.highlights.length > 0 && (
                        <ul className="space-y-2">
                            {program.highlights.map((line) => (
                                <li
                                    key={line}
                                    className="flex items-start gap-2 text-sm"
                                >
                                    <Check className="mt-0.5 size-4 shrink-0 text-lime-600 dark:text-lime-400" />
                                    {line}
                                </li>
                            ))}
                        </ul>
                    )}

                    {program.description !== null && (
                        <p className="text-sm text-muted-foreground">
                            {program.description}
                        </p>
                    )}
                </CardContent>
            </Card>

            <h2 className="mt-6 text-base font-semibold">
                {t('shared_meal_program.inside_program')}
            </h2>
            <div className="mt-3 space-y-3">
                {program.days.map((day, index) => (
                    <Collapsible
                        key={day.day}
                        defaultOpen={index === 0}
                        className="rounded-xl border"
                    >
                        <CollapsibleTrigger className="group flex w-full items-center justify-between gap-2 px-4 py-3 text-left">
                            <div>
                                <p className="text-sm font-medium">
                                    {t('shared_meal_program.day_label', {
                                        day: day.day,
                                    })}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        day.meals.length === 1
                                            ? 'shared_meal_program.meal_count'
                                            : 'shared_meal_program.meals_count',
                                        { count: day.meals.length },
                                    )}
                                </p>
                            </div>
                            <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="divide-y border-t">
                            {day.meals.map((meal, mealIndex) => (
                                <div
                                    key={`${meal.type}-${mealIndex}`}
                                    className="px-4 py-3"
                                >
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {mealTypeLabel(meal.type)}
                                    </p>
                                    <ul className="mt-2 space-y-1.5">
                                        {meal.items.map((item, itemIndex) => (
                                            <li
                                                key={itemIndex}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                {item.image_url !== null &&
                                                item.image_url !== undefined ? (
                                                    <img
                                                        src={item.image_url}
                                                        alt=""
                                                        className="size-8 shrink-0 rounded-md object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-lime-200/60 dark:bg-lime-900/40">
                                                        <UtensilsCrossed className="size-3.5 text-lime-700 dark:text-lime-400" />
                                                    </span>
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    {item.name}
                                                    {item.portion_note !==
                                                        null && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            —{' '}
                                                            {item.portion_note}
                                                        </span>
                                                    )}
                                                </span>
                                                {item.macros?.kcal_per_100g !=
                                                    null && (
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        {
                                                            item.macros
                                                                .kcal_per_100g
                                                        }{' '}
                                                        kcal
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </CollapsibleContent>
                    </Collapsible>
                ))}
            </div>
            {isGuest && (
                <p className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Lock className="size-3.5" />
                    {t('shared_meal_program.login_hint')}
                </p>
            )}

            <div className="h-40" />

            <div className="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 backdrop-blur">
                <div className="mx-auto flex max-w-md items-center gap-3 px-4 py-3">
                    {claimed ? (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    {t('shared_meal_program.in_your_plans')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('shared_meal_program.already_own')}
                                </p>
                            </div>
                            {planUrl !== null && (
                                <Button variant="outline" asChild>
                                    <Link href={planUrl}>
                                        {t('shared_meal_program.open_my_plan')}
                                    </Link>
                                </Button>
                            )}
                        </>
                    ) : isOwner && editUrl !== null ? (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    {t('shared_program.your_shared_program')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('shared_program.viewing_as_visitor')}
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href={editUrl}>
                                    {t('shared_program.edit_program')}
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <div className="min-w-0 flex-1 space-y-2">
                            <p className="text-center text-xs text-muted-foreground">
                                {t('shared_meal_program.pro_badge')} ·{' '}
                                {t('shared_meal_program.pro_included')}
                            </p>
                            {isGuest ? (
                                <Button
                                    className="w-full rounded-full"
                                    asChild
                                >
                                    <Link href={login()}>
                                        {t('shared_program.login_to_save')}
                                    </Link>
                                </Button>
                            ) : isPro ? (
                                <Button
                                    className="w-full rounded-full"
                                    disabled={saving}
                                    onClick={save}
                                >
                                    {saving
                                        ? t('shared_program.saving')
                                        : t(
                                              'shared_meal_program.claim_program',
                                          )}
                                </Button>
                            ) : (
                                <Button
                                    className="w-full rounded-full"
                                    asChild
                                >
                                    <Link href={proUrl}>
                                        {t(
                                            'shared_meal_program.upgrade_to_pro',
                                        )}
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                className="w-full rounded-full"
                                asChild
                            >
                                <Link href={homeUrl}>
                                    {t('shared_program.back_to_app')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </MobileShell>
    );
}
