import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    MEAL_TYPE_TIMES,
    mealItemSummary,
    mealTypeLabel,
    type PlanMeal,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as mealShow } from '@/routes/member/meals';

export function PlanMealsCard({
    planName,
    meals,
    onAdd,
}: {
    planName: string;
    meals: PlanMeal[];
    onAdd: (meal: PlanMeal) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Your plan</CardTitle>
                <CardDescription>{planName}</CardDescription>
            </CardHeader>
            <CardContent className="divide-y divide-[#2E2E2E] p-0">
                {meals.map((m, index) => (
                    <div
                        key={`${m.type}-${index}`}
                        className="flex items-start gap-3 px-6 py-3 transition-colors hover:bg-[#262626]"
                    >
                        <span className="mt-1.5 size-2 shrink-0 rounded-full bg-[#AFFA01] shadow-[0_0_6px_rgba(175,250,1,0.6)]" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-[#F5F5F5]">
                                {m.id !== undefined ? (
                                    <Link
                                        href={mealShow(m.id).url}
                                        className="hover:text-[#AFFA01] transition-colors"
                                    >
                                        {mealTypeLabel(m.type)}
                                    </Link>
                                ) : (
                                    mealTypeLabel(m.type)
                                )}{' '}
                                <span className="font-normal text-[#A1A1AA]">
                                    {MEAL_TYPE_TIMES[m.type] ?? ''}
                                </span>
                            </p>
                            <p className="truncate text-xs text-[#A1A1AA]">
                                {mealItemSummary(m)}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0 text-[#A1A1AA] hover:text-[#AFFA01]"
                            onClick={() => onAdd(m)}
                            aria-label={`Log ${mealTypeLabel(m.type)}`}
                        >
                            <Plus className="size-4" />
                        </Button>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
