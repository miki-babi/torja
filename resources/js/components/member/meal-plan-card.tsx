import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    mealItemSummary,
    mealTypeLabel,
    type PlanDay,
} from '@/components/member/types';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { meal } from '@/routes/member';
import { show as mealShow } from '@/routes/member/meals';

export function MealPlanCard({ planDay }: { planDay: PlanDay | null }) {
    const { t } = useTranslation();

    /**
     * Translated meal-type label (meal_type.*), falling back to the English
     * catalog label when no language line exists.
     */
    function typeLabel(type: string): string {
        const key = `meal_type.${type}`;
        const text = t(key);
        return text === key ? mealTypeLabel(type) : text;
    }

    if (planDay === null || planDay.meals.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col gap-2 p-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('member.home.todays_meal_plan')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('member.home.no_meal_plan')}
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="divide-y divide-[#2E2E2E] p-0">
                <div className="px-5 py-4">
                    <h2 className="text-sm font-semibold tracking-wider text-[#AFFA01] uppercase">
                        {t('member.home.todays_meal_plan')}
                    </h2>
                </div>
                {planDay.meals.map((m, index) => (
                    <Link
                        key={`${m.type}-${index}`}
                        href={
                            m.id !== undefined ? mealShow(m.id).url : meal().url
                        }
                        className="flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-[#262626]"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-[#F5F5F5]">
                                {typeLabel(m.type)}
                            </p>
                            <p className="truncate text-xs text-[#A1A1AA]">
                                {mealItemSummary(m)}
                            </p>
                        </div>
                        <ChevronRight className="size-4 shrink-0 text-[#666666]" />
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
