import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Plus,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { deleteDiaryEntry, logDiaryEntry } from '@/components/member/diary';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    MEAL_TYPE_TIMES,
    mealItemSummary,
    mealTypeLabel,
    type DiaryEntry,
    type PlanMeal,
} from '@/components/member/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLocalStorage } from '@/hooks/use-local-storage';
import { cn } from '@/lib/utils';
import { meal } from '@/routes/member';

type PlanMealItem = PlanMeal['items'][number];

function macroLine(macros: PlanMealItem['macros']): string | null {
    if (macros == null) {
        return null;
    }
    const parts: string[] = [];
    if (macros.kcal_per_100g !== null) {
        parts.push(`${Math.round(macros.kcal_per_100g)} kcal/100g`);
    }
    const macroParts = [
        ['P', macros.protein_g],
        ['C', macros.carbs_g],
        ['F', macros.fat_g],
    ]
        .filter(([, v]) => v !== null)
        .map(([k, v]) => `${k} ${v}g`);
    return [...parts, ...macroParts].join(' · ') || null;
}

function FoodThumb({ url }: { url: string | null | undefined }) {
    return url != null ? (
        <img
            src={url}
            alt=""
            loading="lazy"
            className="size-12 shrink-0 rounded-lg object-cover"
        />
    ) : (
        <span className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-lime-200/60 dark:bg-lime-900/40">
            <UtensilsCrossed className="size-5 text-lime-700 dark:text-lime-400" />
        </span>
    );
}

export default function MemberMealShow({
    planName,
    day,
    meal: planMeal,
}: {
    planName: string;
    day: number;
    meal: PlanMeal;
}) {
    const [, setDiaryByDate] = useLocalStorage<Record<string, DiaryEntry[]>>(
        'member:diary',
        {},
    );
    const [checked, setChecked] = useState<Record<number, boolean>>({});
    const [eaten, setEaten] = useState<
        Record<
            number,
            {
                name: string;
                entryId: number;
                food_id: number | null;
                kind: 'original' | 'alt';
            }
        >
    >({});
    const [busyIndex, setBusyIndex] = useState<number | null>(null);
    const [mealData, setMealData] = useState<PlanMeal>(planMeal);
    const [pickerIndex, setPickerIndex] = useState<number | null>(null);
    const [justChanged, setJustChanged] = useState<number | null>(null);

    function flashChanged(index: number) {
        setJustChanged(index);
        window.setTimeout(
            () => setJustChanged((i) => (i === index ? null : i)),
            750,
        );
    }

    async function pickSwap(index: number, item: PlanMealItem, foodId: number) {
        if (item.item_id === undefined || mealData.id === undefined) {
            return;
        }
        const result = await memberApi<{ meal: PlanMeal }>(
            `/meal/${mealData.id}/items/${item.item_id}/swap`,
            'PUT',
            { food_id: foodId },
        );
        setMealData(result.meal);
        setPickerIndex(null);
        flashChanged(index);
    }

    async function resetSwap(item: PlanMealItem) {
        if (item.item_id === undefined || mealData.id === undefined) {
            return;
        }
        const result = await memberApi<{ meal: PlanMeal }>(
            `/meal/${mealData.id}/items/${item.item_id}/swap`,
            'DELETE',
        );
        setMealData(result.meal);
    }

    async function chooseEaten(
        index: number,
        item: PlanMealItem,
        choice: {
            food_id: number | null;
            name: string;
            kind: 'original' | 'alt';
            excluded?: boolean;
        },
    ) {
        if (busyIndex !== null || choice.excluded === true) {
            return;
        }

        const current = eaten[index];
        setBusyIndex(index);
        try {
            if (current !== undefined) {
                await deleteDiaryEntry(current.entryId);
                setEaten((map) => {
                    const next = { ...map };
                    delete next[index];
                    return next;
                });
                if (
                    current.kind === choice.kind &&
                    current.food_id === choice.food_id
                ) {
                    return;
                }
            }

            if (choice.kind === 'original' && item.chosen === true) {
                await resetSwap(item);
            }

            const entry = await logDiaryEntry({
                logged_on: new Date().toISOString().slice(0, 10),
                name: choice.name,
                detail:
                    choice.kind === 'original'
                        ? item.chosen === true
                            ? `instead of ${item.name}`
                            : 'original plan item'
                        : `instead of ${item.original_name ?? item.name}`,
                kcal: 0,
                protein_g: 0,
                carbs_g: 0,
                fat_g: 0,
                food_id: choice.food_id,
                source:
                    choice.kind === 'alt' || item.chosen === true
                        ? 'plan_swap'
                        : 'plan_meal',
            });
            setEaten((map) => ({
                ...map,
                [index]: {
                    name: choice.name,
                    entryId: entry.id,
                    food_id: choice.food_id,
                    kind: choice.kind,
                },
            }));
            flashChanged(index);
            toast.success(`${choice.name} logged to today's diary`);
        } catch {
            toast.error('Could not log that food');
        } finally {
            setBusyIndex(null);
        }
    }

    const label = mealTypeLabel(planMeal.type);
    const time = MEAL_TYPE_TIMES[planMeal.type];
    const pickerItem =
        pickerIndex !== null ? mealData.items[pickerIndex] : null;

    function logMeal() {
        const dateKey = new Date().toISOString().slice(0, 10);
        const entry: DiaryEntry = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            name: label,
            detail: mealItemSummary(mealData),
            kcal: 0,
            protein: 0,
            carbs: 0,
            fat: 0,
        };
        setDiaryByDate((d) => ({
            ...d,
            [dateKey]: [...(d[dateKey] ?? []), entry],
        }));
        toast.success(`${label} logged to today's diary`);
        router.visit(meal().url);
    }

    return (
        <MobileShell>
            <Head title={label} />
            <Button variant="ghost" size="sm" asChild className="-ml-2">
                <Link href={meal()}>
                    <ChevronLeft className="size-4" />
                    Nutrition
                </Link>
            </Button>

            <div className="mt-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {label}
                    {time !== undefined && (
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            {time}
                        </span>
                    )}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {planName} · Day {day}
                </p>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base">Items</CardTitle>
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {mealData.items.map((item, index) => {
                        const eatenAlt = eaten[index];
                        const isChecked =
                            eatenAlt !== undefined || (checked[index] ?? false);
                        const displayName = eatenAlt?.name ?? item.name;
                        const altCount = item.swap_options?.length ?? 0;
                        return (
                            <div
                                key={index}
                                className={cn(
                                    'flex flex-col px-5 py-4 transition-colors',
                                    justChanged === index &&
                                        'animate-meal-row-pulse rounded-lg',
                                )}
                            >
                                <div className="flex items-center gap-3">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            eatenAlt !== undefined
                                                ? void chooseEaten(
                                                      index,
                                                      item,
                                                      {
                                                          food_id:
                                                              eatenAlt.food_id,
                                                          name: eatenAlt.name,
                                                          kind: eatenAlt.kind,
                                                      },
                                                  )
                                                : setChecked((c) => ({
                                                      ...c,
                                                      [index]: !isChecked,
                                                  }))
                                        }
                                        aria-label={`Mark ${displayName} as eaten`}
                                        aria-pressed={isChecked}
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-md border',
                                            isChecked
                                                ? 'border-lime-500 bg-lime-400 text-lime-950'
                                                : 'border-input',
                                        )}
                                    >
                                        {isChecked && (
                                            <Check className="animate-check-pop size-4" />
                                        )}
                                    </button>
                                    <FoodThumb url={item.image_url} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <p
                                                key={displayName}
                                                className={cn(
                                                    'animate-meal-swap-in text-sm font-medium',
                                                    (item.excluded ||
                                                        isChecked) &&
                                                        'text-muted-foreground line-through',
                                                )}
                                            >
                                                {displayName}
                                            </p>
                                            {item.excluded === true && (
                                                <Badge variant="outline">
                                                    excluded
                                                </Badge>
                                            )}
                                        </div>
                                        {item.portion_note !== null && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {item.portion_note}
                                            </p>
                                        )}
                                        {macroLine(item.macros) !== null && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {macroLine(item.macros)}
                                            </p>
                                        )}
                                        {item.swapped_from != null && (
                                            <p className="mt-1 text-xs text-lime-700 dark:text-lime-400">
                                                Swapped for{' '}
                                                {item.swapped_from.name}
                                                {item.swapped_from.reason !==
                                                    null &&
                                                    ` — ${item.swapped_from.reason}`}
                                            </p>
                                        )}
                                        {eatenAlt !== undefined && (
                                            <p className="mt-1 text-xs text-lime-700 dark:text-lime-400">
                                                {eatenAlt.kind === 'original'
                                                    ? `Ate ${eatenAlt.name}`
                                                    : `Ate this instead of ${item.original_name ?? item.name}`}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                {altCount > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setPickerIndex(index)}
                                        className="mt-2 ml-24 flex w-fit items-center gap-1 rounded-full border border-input px-2.5 py-1 text-xs text-muted-foreground"
                                    >
                                        {altCount} alternative
                                        {altCount > 1 ? 's' : ''}
                                        <ChevronRight className="size-3" />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <Sheet
                open={pickerIndex !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPickerIndex(null);
                    }
                }}
            >
                <SheetContent
                    side="bottom"
                    className="mx-auto max-h-[80vh] max-w-md rounded-t-2xl px-0 pb-6"
                >
                    {pickerItem !== null && pickerIndex !== null && (
                        <>
                            <SheetHeader className="px-5">
                                <SheetTitle className="text-base">
                                    Alternatives
                                </SheetTitle>
                                <SheetDescription className="truncate">
                                    For{' '}
                                    {pickerItem.original_name ??
                                        pickerItem.name}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="mt-2 max-h-[60vh] space-y-2 overflow-y-auto px-5">
                                {(() => {
                                    const originalFoodId =
                                        pickerItem.original_food_id ??
                                        pickerItem.food_id ??
                                        null;
                                    const isEaten =
                                        eaten[pickerIndex]?.kind === 'original';
                                    return (
                                        <div className="flex items-center gap-3 rounded-xl border p-3">
                                            <Checkbox
                                                checked={isEaten}
                                                disabled={
                                                    busyIndex === pickerIndex
                                                }
                                                onCheckedChange={() =>
                                                    void chooseEaten(
                                                        pickerIndex,
                                                        pickerItem,
                                                        {
                                                            food_id:
                                                                originalFoodId,
                                                            name:
                                                                pickerItem.original_name ??
                                                                pickerItem.name,
                                                            kind: 'original',
                                                        },
                                                    )
                                                }
                                                aria-label={`Ate ${pickerItem.original_name ?? pickerItem.name}`}
                                                className="size-6 rounded-md data-[state=checked]:border-lime-500 data-[state=checked]:bg-lime-400 data-[state=checked]:text-lime-950"
                                            />
                                            <FoodThumb
                                                url={
                                                    pickerItem.original_image_url ??
                                                    pickerItem.image_url
                                                }
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium">
                                                    <span className="underline underline-offset-2">
                                                        Original:{' '}
                                                        {pickerItem.original_name ??
                                                            pickerItem.name}
                                                    </span>
                                                </p>
                                                {macroLine(
                                                    pickerItem.original_macros ??
                                                        pickerItem.macros,
                                                ) !== null && (
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {macroLine(
                                                            pickerItem.original_macros ??
                                                                pickerItem.macros,
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })()}
                                {(pickerItem.swap_options ?? []).map((opt) => {
                                    const isEaten =
                                        eaten[pickerIndex]?.kind === 'alt' &&
                                        eaten[pickerIndex]?.food_id ===
                                            opt.food_id;
                                    return (
                                        <div
                                            key={opt.food_id}
                                            className="flex gap-3 rounded-xl border p-3"
                                        >
                                            <Checkbox
                                                checked={isEaten}
                                                disabled={
                                                    opt.excluded === true ||
                                                    busyIndex === pickerIndex
                                                }
                                                onCheckedChange={() =>
                                                    void chooseEaten(
                                                        pickerIndex,
                                                        pickerItem,
                                                        {
                                                            food_id:
                                                                opt.food_id,
                                                            name: opt.name,
                                                            kind: 'alt',
                                                            excluded:
                                                                opt.excluded,
                                                        },
                                                    )
                                                }
                                                aria-label={`Ate ${opt.name}`}
                                                className="mt-2.5 size-6 shrink-0 rounded-md data-[state=checked]:border-lime-500 data-[state=checked]:bg-lime-400 data-[state=checked]:text-lime-950"
                                            />
                                            <FoodThumb url={opt.image_url} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="text-sm font-medium">
                                                        {opt.name}
                                                    </p>
                                                    {opt.excluded === true && (
                                                        <Badge variant="outline">
                                                            not for you
                                                        </Badge>
                                                    )}
                                                </div>
                                                {macroLine(opt.macros) !==
                                                    null && (
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {macroLine(opt.macros)}
                                                    </p>
                                                )}
                                                <div className="mt-2 flex items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 text-xs"
                                                        disabled={
                                                            opt.excluded ===
                                                                true ||
                                                            opt.food_id ===
                                                                pickerItem.food_id
                                                        }
                                                        onClick={() =>
                                                            void pickSwap(
                                                                pickerIndex,
                                                                pickerItem,
                                                                opt.food_id,
                                                            ).catch(() =>
                                                                toast.error(
                                                                    'Could not swap that food',
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Use in plan
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            <Button
                onClick={logMeal}
                className="mt-4 w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
            >
                <Plus className="size-4" />
                Log this meal
            </Button>
        </MobileShell>
    );
}
