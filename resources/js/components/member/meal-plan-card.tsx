import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    mealItemSummary,
    mealTypeLabel,
    type PlanDay,
} from '@/components/member/types';
import { Card, CardContent } from '@/components/ui/card';
import { meal } from '@/routes/member';
import { show as mealShow } from '@/routes/member/meals';
import { wizard } from '@/routes/plan';

export function MealPlanCard({ planDay }: { planDay: PlanDay | null }) {
    if (planDay === null || planDay.meals.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col gap-2 p-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Today's meal plan
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        No meal plan yet.{' '}
                        <Link href={wizard()} className="underline">
                            Take the quiz
                        </Link>{' '}
                        to get one.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="divide-y p-0">
                <div className="px-4 py-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Today's meal plan
                    </h2>
                </div>
                {planDay.meals.map((m, index) => (
                    <Link
                        key={`${m.type}-${index}`}
                        href={
                            m.id !== undefined ? mealShow(m.id).url : meal().url
                        }
                        className="flex items-center gap-3 px-4 py-3 hover:bg-accent/50"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">
                                {mealTypeLabel(m.type)}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {mealItemSummary(m)}
                            </p>
                        </div>
                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
