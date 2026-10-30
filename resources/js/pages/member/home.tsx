import { Head, Link } from '@inertiajs/react';
import { Compass, Dumbbell, Flame, Sparkles, Utensils } from 'lucide-react';
import { MealPlanCard } from '@/components/member/meal-plan-card';
import { MobileShell } from '@/components/member/mobile-shell';
import { TodaysWorkoutCard } from '@/components/member/todays-workout-card';
import type { PlanDay } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { exercise, meal, today } from '@/routes/member';
import { index as exploreIndex } from '@/routes/explore';

function getGreeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

export default function MemberHome({
    userName,
    planDay,
    todayWorkout,
    streak,
}: {
    userName: string;
    planDay: PlanDay | null;
    todayWorkout: { title: string; subtitle: string };
    streak: number;
}) {
    const { t } = useTranslation();
    const greeting = getGreeting();

    return (
        <MobileShell>
            <Head title="Home" />

            {/* PrimeGym Header */}
            <div className="flex items-center justify-between pt-2">
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-[#F5F5F5]">
                        {greeting}, {userName} 👋
                    </h1>
                    <div className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]">
                        <Flame className="size-3.5 fill-[#AFFA01] text-[#AFFA01]" />
                        <span>{streak} day streak</span>
                    </div>
                </div>
            </div>

            {/* PrimeGym Hero Today's Workout Card */}
            <section className="mt-5">
                <TodaysWorkoutCard
                    todayLabel="TODAY"
                    title={todayWorkout.title}
                    subtitle={todayWorkout.subtitle || '0 / 1 workout'}
                    ctaLabel="Start Workout"
                    href={today()}
                />
            </section>

            {/* Quick Action Chips */}
            <div className="mt-4 flex scrollbar-none items-center gap-2 overflow-x-auto pb-1">
                <Link
                    href={exercise()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Sparkles className="size-3.5 text-[#AFFA01]" />
                    <span>Exercises</span>
                </Link>
                <Link
                    href={today()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#AFFA01]/30 bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/60"
                >
                    <Dumbbell className="size-3.5 text-[#AFFA01]" />
                    <span>Workout</span>
                </Link>
                <Link
                    href={meal()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Utensils className="size-3.5 text-[#AFFA01]" />
                    <span>Meal Plan</span>
                </Link>
                <Link
                    href={exploreIndex()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Compass className="size-3.5 text-[#AFFA01]" />
                    <span>Programs</span>
                </Link>
            </div>

            {/* Meal Plan Section */}
            <section className="mt-5">
                <MealPlanCard planDay={planDay} />
            </section>

            {/* Programs Section */}
            <section className="mt-5">
                <Card className="overflow-hidden p-0">
                    <CardContent className="flex items-center justify-between gap-4 p-5">
                        <div className="min-w-0 flex-1">
                            <span className="text-[11px] font-bold tracking-wider text-[#AFFA01] uppercase">
                                {t('programs.home_card_title')}
                            </span>
                            <p className="mt-1 text-sm font-medium text-[#F5F5F5]">
                                {t('programs.home_card_body')}
                            </p>
                        </div>
                        <Button asChild size="sm" className="shrink-0">
                            <Link href={exploreIndex()}>
                                {t('programs.browse')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </MobileShell>
    );
}
