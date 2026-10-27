import { Head, Link } from '@inertiajs/react';
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Lock,
    UtensilsCrossed,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { FoodDiaryCard } from '@/components/member/food-diary-card';
import { LogFoodDialog } from '@/components/member/log-food-dialog';
import { MobileShell } from '@/components/member/mobile-shell';
import { PlanMealsCard } from '@/components/member/plan-meals-card';
import { TargetsCard } from '@/components/member/targets-card';
import {
    mealItemSummary,
    mealTypeLabel,
    type DiaryEntry,
    type DiaryTotals,
    type NutritionTargets,
    type PlanDay,
    type PlanMeal,
} from '@/components/member/types';
import { WaterCard } from '@/components/member/water-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useLocalStorage } from '@/hooks/use-local-storage';
import { useTranslation } from '@/lib/i18n';
import {
    destroy as destroyDiaryEntry,
    index as diaryIndex,
    store as storeDiaryEntry,
} from '@/routes/member/diary';
import { wizard } from '@/routes/plan';
import { show as pro } from '@/routes/pro';

function newId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

type ServerEntry = {
    id: number;
    name: string;
    detail: string | null;
    kcal: string | null;
    protein_g: string | null;
    carbs_g: string | null;
    fat_g: string | null;
    food_id: number | null;
    source: string;
};

function toDiaryEntry(e: ServerEntry): DiaryEntry {
    return {
        id: `srv-${e.id}`,
        name: e.name,
        detail: e.detail ?? '',
        kcal: Number(e.kcal ?? 0),
        protein: Number(e.protein_g ?? 0),
        carbs: Number(e.carbs_g ?? 0),
        fat: Number(e.fat_g ?? 0),
    };
}

export default function MemberMeal({
    targets,
    planDays,
    unlocked,
    price,
    claimedPrograms,
}: {
    targets: NutritionTargets | null;
    planDays: Record<string, PlanDay | null>;
    unlocked: boolean;
    price: number;
    claimedPrograms: {
        id: number;
        name: string;
        days_count: number;
        image_url: string | null;
        url: string;
    }[];
}) {
    const { t } = useTranslation();
    const [offset, setOffset] = useState(0);
    const [logOpen, setLogOpen] = useState(false);
    const [diaryByDate, setDiaryByDate] = useLocalStorage<
        Record<string, DiaryEntry[]>
    >('member:diary', {});
    const [serverEntries, setServerEntries] = useState<DiaryEntry[]>([]);

    const date = new Date();
    date.setDate(date.getDate() + offset);
    const dateKey = date.toISOString().slice(0, 10);
    const dayNumber = (((offset % 7) + 7) % 7) + 1;
    const planDay = planDays[String(dayNumber)] ?? null;
    const dateLabel =
        offset === 0
            ? 'Today'
            : offset === -1
              ? 'Yesterday'
              : offset === 1
                ? 'Tomorrow'
                : date.toLocaleDateString(undefined, {
                      month: 'short',
                      day: 'numeric',
                  });

    useEffect(() => {
        let cancelled = false;

        fetch(`${diaryIndex().url}?date=${dateKey}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : { entries: [] }))
            .then((data: { entries: ServerEntry[] }) => {
                if (!cancelled) {
                    setServerEntries(data.entries.map(toDiaryEntry));
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
        };
    }, [dateKey]);

    // Server is the source of truth for new entries; `member:diary` only
    // still carries entries logged before server persistence existed.
    const legacyEntries = diaryByDate[dateKey] ?? [];
    const entries = [...serverEntries, ...legacyEntries];
    const setEntries = (next: DiaryEntry[]) =>
        setDiaryByDate((d) => ({ ...d, [dateKey]: next }));

    const consumed: DiaryTotals = entries.reduce(
        (acc, e) => ({
            kcal: acc.kcal + e.kcal,
            protein: acc.protein + e.protein,
            carbs: acc.carbs + e.carbs,
            fat: acc.fat + e.fat,
        }),
        { kcal: 0, protein: 0, carbs: 0, fat: 0 },
    );

    const addEntry = (entry: Omit<DiaryEntry, 'id'>) =>
        persistEntry(entry, 'search');
    const addPlanMeal = (m: PlanMeal) =>
        persistEntry(
            {
                name: mealTypeLabel(m.type),
                detail: mealItemSummary(m),
                kcal: 0,
                protein: 0,
                carbs: 0,
                fat: 0,
            },
            'plan_meal',
        );

    function persistEntry(entry: Omit<DiaryEntry, 'id'>, source: string) {
        const optimistic = { ...entry, id: newId() };
        setServerEntries((list) => [...list, optimistic]);

        fetch(storeDiaryEntry().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                logged_on: dateKey,
                name: entry.name,
                detail: entry.detail,
                kcal: entry.kcal,
                protein_g: entry.protein,
                carbs_g: entry.carbs,
                fat_g: entry.fat,
                source,
                had_photos: (entry.photos?.length ?? 0) > 0,
            }),
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data: { entry: ServerEntry } | null) => {
                if (data) {
                    setServerEntries((list) =>
                        list.map((e) =>
                            e.id === optimistic.id
                                ? toDiaryEntry(data.entry)
                                : e,
                        ),
                    );
                }
            })
            .catch(() => undefined);
    }

    const removeEntry = (id: string) => {
        if (id.startsWith('srv-')) {
            const numericId = Number(id.slice(4));
            setServerEntries((list) => list.filter((e) => e.id !== id));
            fetch(destroyDiaryEntry(numericId).url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            }).catch(() => undefined);

            return;
        }

        setEntries(legacyEntries.filter((e) => e.id !== id));
    };

    return (
        <MobileShell>
            <Head title="Nutrition" />
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold tracking-tight">
                    Nutrition
                </h1>
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setOffset((o) => o - 1)}
                        aria-label="Previous day"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="w-20 text-center text-sm">
                        {dateLabel}
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setOffset((o) => o + 1)}
                        aria-label="Next day"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="mt-4">
                <Button
                    onClick={() => setLogOpen(true)}
                    className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                >
                    <Camera className="size-4" />
                    Log food
                </Button>
                <p className="mt-1 text-center text-xs text-muted-foreground">
                    take a photo or search the database
                </p>
            </div>

            <div className="mt-4 space-y-4">
                {targets === null ? (
                    <Card>
                        <CardContent className="p-4 text-sm text-muted-foreground">
                            Complete the quiz to get your daily targets.{' '}
                            <Link href={wizard()} className="underline">
                                Take the quiz
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <TargetsCard targets={targets} consumed={consumed} />
                )}

                <WaterCard dateKey={dateKey} />

                {planDay !== null ? (
                    <PlanMealsCard
                        planName={planDay.plan_name}
                        meals={planDay.meals}
                        onAdd={addPlanMeal}
                    />
                ) : (
                    !unlocked &&
                    targets !== null && (
                        <Card>
                            <CardContent className="flex flex-col items-center gap-2 p-4 text-center">
                                <Lock className="size-5 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    This day is part of your full plan
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Unlock all 7 days of meals for{' '}
                                    {price.toLocaleString()} ETB.
                                </p>
                                <Button
                                    asChild
                                    className="mt-1 bg-lime-400 text-lime-950 hover:bg-lime-500"
                                >
                                    <Link href={pro()}>Go Pro</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )
                )}

                {claimedPrograms.length > 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm font-medium">
                                {t('meal_program.my_programs')}
                            </p>
                            <div className="mt-3 space-y-2">
                                {claimedPrograms.map((program) => (
                                    <Link
                                        key={program.id}
                                        href={program.url}
                                        className="flex items-center gap-3 rounded-lg border p-2 hover:bg-accent/50"
                                    >
                                        {program.image_url !== null ? (
                                            <img
                                                src={program.image_url}
                                                alt=""
                                                className="size-10 shrink-0 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-lime-200/60 dark:bg-lime-900/40">
                                                <UtensilsCrossed className="size-4 text-lime-700 dark:text-lime-400" />
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {program.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('meal_program.days_count', {
                                                    count: program.days_count,
                                                })}
                                            </p>
                                        </div>
                                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <FoodDiaryCard
                    entries={entries}
                    onLogFood={() => setLogOpen(true)}
                    onRemove={removeEntry}
                />
            </div>

            <LogFoodDialog
                open={logOpen}
                onOpenChange={setLogOpen}
                onAdd={addEntry}
            />
        </MobileShell>
    );
}
