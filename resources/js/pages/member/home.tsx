import { Head, Link } from '@inertiajs/react';
import {
    Compass,
    Dumbbell,
    Flame,
    Sparkles,
    TrendingUp,
    Utensils,
    UtensilsCrossed,
} from 'lucide-react';
import {
    BannerCarousel,
    type HomeBanner,
} from '@/components/member/banner-carousel';
import { MealPlanCard } from '@/components/member/meal-plan-card';
import { MobileShell } from '@/components/member/mobile-shell';
import { TodaysWorkoutCard } from '@/components/member/todays-workout-card';
import type { PlanDay } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { exercise, meal, progress, today } from '@/routes/member';
import { create as createPlan } from '@/routes/member/meal-plans';
import { index as exploreIndex } from '@/routes/explore';

function greetingKey(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'greeting_morning';
    if (hour < 17) return 'greeting_afternoon';
    return 'greeting_evening';
}

export default function MemberHome({
    userName,
    planDay,
    banners,
    streak,
}: {
    userName: string;
    planDay: PlanDay | null;
    banners: HomeBanner[];
    streak: number;
}) {
    const { t } = useTranslation();
    const greeting = t(`member.home.${greetingKey()}`);

    return (
        <MobileShell>
            <Head title={t('member.home.title')} />

            {/* PrimeGym Header */}
            <div className="flex items-center justify-between pt-2">
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-[#F5F5F5]">
                        {greeting}, {userName} 👋
                    </h1>
                    <div className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]">
                        <Flame className="size-3.5 fill-[#AFFA01] text-[#AFFA01]" />
                        <span>
                            {t('member.home.day_streak', { count: streak })}
                        </span>
                    </div>
                </div>
            </div>

            {/* PrimeGym Hero Workout Builder Card */}
            <section className="mt-5">
                <TodaysWorkoutCard
                    todayLabel={t('member.home.today')}
                    title={t('member.builder.title')}
                    subtitle={t('member.builder.subtitle')}
                    ctaLabel={t('member.home.start_workout')}
                    href={today().url}
                />
            </section>

            {/* Quick Action Chips */}
            <div className="mt-4 flex scrollbar-none items-center gap-2 overflow-x-auto pb-1">
                <Link
                    href={exercise()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Sparkles className="size-3.5 text-[#AFFA01]" />
                    <span>{t('member.home.chip_exercises')}</span>
                </Link>
                <Link
                    href={today()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#AFFA01]/30 bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/60"
                >
                    <Dumbbell className="size-3.5 text-[#AFFA01]" />
                    <span>{t('member.home.chip_workout')}</span>
                </Link>
                <Link
                    href={progress()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <TrendingUp className="size-3.5 text-[#AFFA01]" />
                    <span>{t('member.home.chip_progress')}</span>
                </Link>
                <Link
                    href={meal()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Utensils className="size-3.5 text-[#AFFA01]" />
                    <span>{t('member.home.chip_meal_plan')}</span>
                </Link>
                <Link
                    href={exploreIndex()}
                    className="flex shrink-0 items-center gap-1.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] px-3.5 py-2 text-xs font-semibold text-[#F5F5F5] transition-colors hover:border-[#AFFA01]/30"
                >
                    <Compass className="size-3.5 text-[#AFFA01]" />
                    <span>{t('member.home.chip_programs')}</span>
                </Link>
            </div>

            {/* Ad Banner Carousel */}
            {banners.length > 0 && (
                <section className="mt-4">
                    <BannerCarousel banners={banners} />
                </section>
            )}

            {/* Meal Plan Section */}
            <section className="mt-5">
                {planDay !== null ? (
                    <MealPlanCard planDay={planDay} />
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 p-4 text-center">
                            <UtensilsCrossed className="size-5 text-muted-foreground" />
                            <p className="text-sm font-medium">
                                {t('member.meal_builder.no_plan')}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('member.meal_builder.no_plan_hint')}
                            </p>
                            <Button asChild className="mt-2">
                                <Link href={createPlan().url}>
                                    {t('member.meal_builder.create_plan')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </section>
        </MobileShell>
    );
}
