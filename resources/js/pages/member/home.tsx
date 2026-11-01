import { Head, Link } from '@inertiajs/react';
import {
    ChevronRight,
    Compass,
    Dumbbell,
    Flame,
    Sparkles,
    TrendingUp,
} from 'lucide-react';
import {
    BannerCarousel,
    type HomeBanner,
} from '@/components/member/banner-carousel';
import { MobileShell } from '@/components/member/mobile-shell';
import { TodaysWorkoutCard } from '@/components/member/todays-workout-card';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { exercise, progress, today } from '@/routes/member';
import { index as exploreIndex } from '@/routes/explore';

function greetingKey(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'greeting_morning';
    if (hour < 17) return 'greeting_afternoon';
    return 'greeting_evening';
}

export default function MemberHome({
    userName,
    weekStats,
    banners,
    streak,
}: {
    userName: string;
    weekStats: { sessions: number; volume: number };
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

            {/* Weekly progress snapshot */}
            <section className="mt-5">
                <Link href={progress()} className="block">
                    <Card className="transition-colors hover:border-[#AFFA01]/40">
                        <CardContent className="flex items-center gap-3 p-4">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#AFFA01]/10 text-[#AFFA01]">
                                <TrendingUp className="size-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-[#F5F5F5]">
                                    {t('member.home.week_title')}
                                </p>
                                <p className="text-xs text-[#8E8E93]">
                                    {t('member.home.week_stats', {
                                        sessions: weekStats.sessions,
                                        volume: Math.round(
                                            weekStats.volume,
                                        ).toLocaleString(),
                                    })}
                                </p>
                            </div>
                            <ChevronRight className="size-4 shrink-0 text-[#8E8E93]" />
                        </CardContent>
                    </Card>
                </Link>
            </section>
        </MobileShell>
    );
}
