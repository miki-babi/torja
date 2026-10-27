import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { logDiaryEntry } from '@/components/member/diary';
import { MobileShell } from '@/components/member/mobile-shell';
import { PlanMealsCard } from '@/components/member/plan-meals-card';
import {
    mealItemSummary,
    mealTypeLabel,
    type PlanDay,
    type PlanMeal,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    plan: {
        id: number;
        name: string;
        image_url: string | null;
    };
    days: Record<string, PlanDay>;
};

export default function MemberMealProgram({ plan, days }: Props) {
    const { t } = useTranslation();
    const dayNumbers = Object.keys(days)
        .map(Number)
        .sort((a, b) => a - b);
    const [selectedDay, setSelectedDay] = useState<number>(dayNumbers[0] ?? 1);
    const [logging, setLogging] = useState(false);

    const planDay = days[String(selectedDay)] ?? null;

    async function logMeal(meal: PlanMeal) {
        if (logging) {
            return;
        }
        setLogging(true);
        try {
            await logDiaryEntry({
                logged_on: new Date().toISOString().slice(0, 10),
                name: mealTypeLabel(meal.type),
                detail: mealItemSummary(meal),
                kcal: 0,
                protein_g: 0,
                carbs_g: 0,
                fat_g: 0,
                source: 'plan_meal',
            });
            toast.success(t('meal_program.logged_toast'));
        } catch {
            toast.error(t('meal_program.log_failed_toast'));
        } finally {
            setLogging(false);
        }
    }

    return (
        <MobileShell>
            <Head title={plan.name} />

            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-tight">
                        {plan.name}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        {t('meal_program.subtitle')}
                    </p>
                </div>
                {plan.image_url !== null && (
                    <img
                        src={plan.image_url}
                        alt=""
                        className="size-12 shrink-0 rounded-xl object-cover"
                    />
                )}
            </div>

            <div className="mt-4 flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0"
                    disabled={selectedDay <= (dayNumbers[0] ?? 1)}
                    onClick={() =>
                        setSelectedDay((d) =>
                            Math.max(d - 1, dayNumbers[0] ?? 1),
                        )
                    }
                    aria-label="Previous day"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <div className="flex flex-1 gap-1 overflow-x-auto">
                    {dayNumbers.map((day) => (
                        <button
                            key={day}
                            type="button"
                            onClick={() => setSelectedDay(day)}
                            className={cn(
                                'shrink-0 rounded-full border px-3 py-1.5 text-xs',
                                day === selectedDay
                                    ? 'border-lime-500 bg-lime-300/40 font-medium'
                                    : 'border-input text-muted-foreground',
                            )}
                        >
                            {t('meal_program.day_label', { day })}
                        </button>
                    ))}
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0"
                    disabled={
                        selectedDay >= (dayNumbers[dayNumbers.length - 1] ?? 1)
                    }
                    onClick={() =>
                        setSelectedDay((d) =>
                            Math.min(
                                d + 1,
                                dayNumbers[dayNumbers.length - 1] ?? 1,
                            ),
                        )
                    }
                    aria-label="Next day"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            {planDay !== null && (
                <div className="mt-4">
                    <PlanMealsCard
                        planName={planDay.plan_name}
                        meals={planDay.meals}
                        onAdd={logMeal}
                    />
                </div>
            )}
        </MobileShell>
    );
}
