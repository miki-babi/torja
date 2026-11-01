import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { deleteDiaryEntry, logDiaryEntry } from '@/components/member/diary';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    MEAL_TYPE_TIMES,
    mealItemSummary,
    mealTypeLabel,
    type DiaryEntry,
    type PlanMeal,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useLocalStorage } from '@/hooks/use-local-storage';
import { cn } from '@/lib/utils';
import { meal } from '@/routes/member';

type PlanMealItem = PlanMeal['items'][number];

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
    const [eaten, setEaten] = useState<Record<number, number>>({});
    const [busyIndex, setBusyIndex] = useState<number | null>(null);

    async function toggleEaten(index: number, item: PlanMealItem) {
        if (busyIndex !== null) {
            return;
        }

        const current = eaten[index];
        setBusyIndex(index);
        try {
            if (current !== undefined) {
                await deleteDiaryEntry(current);
                setEaten((map) => {
                    const next = { ...map };
                    delete next[index];
                    return next;
                });
                return;
            }

            const entry = await logDiaryEntry({
                logged_on: new Date().toISOString().slice(0, 10),
                name: item.name,
                detail: item.portion_note ?? 'plan item',
                kcal: 0,
                protein_g: 0,
                carbs_g: 0,
                fat_g: 0,
                source: 'plan_meal',
            });
            setEaten((map) => ({ ...map, [index]: entry.id }));
            toast.success(`${item.name} logged to today's diary`);
        } catch {
            toast.error('Could not log that food');
        } finally {
            setBusyIndex(null);
        }
    }

    const label = mealTypeLabel(planMeal.type);
    const time = MEAL_TYPE_TIMES[planMeal.type];

    function logMeal() {
        const dateKey = new Date().toISOString().slice(0, 10);
        const entry: DiaryEntry = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            name: label,
            detail: mealItemSummary(planMeal),
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
            <div>
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
                    {planMeal.items.map((item, index) => {
                        const isEaten = eaten[index] !== undefined;
                        return (
                            <div
                                key={index}
                                className="flex items-center gap-3 px-5 py-4"
                            >
                                <button
                                    type="button"
                                    onClick={() =>
                                        void toggleEaten(index, item)
                                    }
                                    disabled={busyIndex === index}
                                    aria-label={`Mark ${item.name} as eaten`}
                                    aria-pressed={isEaten}
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-md border',
                                        isEaten
                                            ? 'border-lime-500 bg-lime-400 text-lime-950'
                                            : 'border-input',
                                    )}
                                >
                                    {isEaten && (
                                        <Check className="animate-check-pop size-4" />
                                    )}
                                </button>
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'text-sm font-medium',
                                            isEaten &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {item.name}
                                    </p>
                                    {item.portion_note !== null && (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {item.portion_note}
                                        </p>
                                    )}
                                    {isEaten && (
                                        <p className="mt-1 text-xs text-lime-700 dark:text-lime-400">
                                            Ate {item.name}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <Button
                className="mt-4 w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                onClick={logMeal}
            >
                Log this meal
            </Button>
        </MobileShell>
    );
}
