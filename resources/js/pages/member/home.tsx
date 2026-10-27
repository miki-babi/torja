import { Head, Link } from '@inertiajs/react';
import { HabitsCard } from '@/components/member/habits-card';
import { MealPlanCard } from '@/components/member/meal-plan-card';
import { MobileShell } from '@/components/member/mobile-shell';
import type { HomeHabit, PlanDay } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { today } from '@/routes/member';
import { index as programsIndex } from '@/routes/programs';

export default function MemberHome({
    userName,
    planDay,
    todayWorkout,
    habits,
}: {
    userName: string;
    planDay: PlanDay | null;
    todayWorkout: { title: string; subtitle: string };
    habits: HomeHabit[];
}) {
    const { t } = useTranslation();

    return (
        <MobileShell>
            <Head title="Home" />
            <h1 className="text-xl font-semibold tracking-tight">
                Welcome, {userName}
            </h1>

            <section className="mt-6">
                <Card>
                    <CardContent className="flex flex-col gap-2 p-4">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Today's exercise
                        </h2>
                        <p className="text-lg font-semibold">
                            {todayWorkout.title}
                        </p>
                        <div className="mt-2 flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                {todayWorkout.subtitle}
                            </p>
                            <Button
                                asChild
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                            >
                                <Link href={today()}>Start now</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section className="mt-6">
                <MealPlanCard planDay={planDay} />
            </section>

            <section className="mt-6">
                <HabitsCard habits={habits} />
            </section>

            <section className="mt-6">
                <Card>
                    <CardContent className="flex items-center justify-between gap-4 p-4">
                        <div>
                            <h2 className="text-sm font-medium text-muted-foreground">
                                {t('programs.home_card_title')}
                            </h2>
                            <p className="mt-1 text-sm">
                                {t('programs.home_card_body')}
                            </p>
                        </div>
                        <Button
                            asChild
                            className="shrink-0 bg-lime-400 text-lime-950 hover:bg-lime-500"
                        >
                            <Link href={programsIndex()}>
                                {t('programs.browse')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </MobileShell>
    );
}
