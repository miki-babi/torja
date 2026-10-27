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
            <CardContent className="divide-y p-0">
                {meals.map((m, index) => (
                    <div
                        key={`${m.type}-${index}`}
                        className="flex items-start gap-3 px-6 py-3"
                    >
                        <span className="mt-1.5 size-2 shrink-0 rounded-full bg-lime-400" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">
                                {m.id !== undefined ? (
                                    <Link
                                        href={mealShow(m.id).url}
                                        className="hover:underline"
                                    >
                                        {mealTypeLabel(m.type)}
                                    </Link>
                                ) : (
                                    mealTypeLabel(m.type)
                                )}{' '}
                                <span className="font-normal text-muted-foreground">
                                    {MEAL_TYPE_TIMES[m.type] ?? ''}
                                </span>
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {mealItemSummary(m)}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0"
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
