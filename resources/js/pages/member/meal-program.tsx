import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    MEAL_TYPE_TIMES,
    mealItemSummary,
    mealTypeLabel,
    type PlanDay,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

    const planDay = days[String(selectedDay)] ?? null;

    return (
        <MobileShell>
            <Head title={plan.name} />

            <div className="mb-3 flex items-center justify-end">
                <span className="inline-flex items-center rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-3 py-1 text-xs font-semibold text-[#AFFA01]">
                    Meal Plan
                </span>
            </div>

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
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle className="text-base">Your plan</CardTitle>
                        <CardDescription>{planDay.plan_name}</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y divide-[#2E2E2E] p-0">
                        {planDay.meals.map((m, index) => (
                            <div
                                key={`${m.type}-${index}`}
                                className="flex items-start gap-3 px-6 py-3"
                            >
                                <span className="mt-1.5 size-2 shrink-0 rounded-full bg-[#AFFA01]" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-[#F5F5F5]">
                                        {mealTypeLabel(m.type)}{' '}
                                        <span className="font-normal text-[#A1A1AA]">
                                            {MEAL_TYPE_TIMES[m.type] ?? ''}
                                        </span>
                                    </p>
                                    <p className="truncate text-xs text-[#A1A1AA]">
                                        {mealItemSummary(m)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </MobileShell>
    );
}
