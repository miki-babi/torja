import { Head, Link, router } from '@inertiajs/react';
import {
    Compass,
    Dumbbell,
    Flame,
    MessageCircle,
    NotebookPen,
    Sparkles,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    BannerCarousel,
    type HomeBanner,
} from '@/components/member/banner-carousel';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Button } from '@/components/ui/button';
import { dm } from '@/routes/member/messages';
import { RecapFeed } from '@/components/member/recap-feed';
import { TodaysWorkoutCard } from '@/components/member/todays-workout-card';
import type { Recap } from '@/components/member/types';
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
    recaps,
    recapsNextCursor,
    coaches,
}: {
    userName: string;
    weekStats: { sessions: number; volume: number };
    banners: HomeBanner[];
    streak: number;
    recaps: Recap[];
    recapsNextCursor: number | null;
    coaches: {
        id: number;
        name: string;
        avatar: string | null;
        slug: string;
        user_id: number;
    }[];
}) {
    const { t } = useTranslation();
    const greeting = t(`member.home.${greetingKey()}`);
    const [messaging, setMessaging] = useState(false);

    async function messageCoach(userId: number) {
        setMessaging(true);
        try {
            const { conversation_id } = await memberApi<{
                conversation_id: number;
            }>(dm().url, 'POST', { user_id: userId });
            router.visit(`/member/messages/${conversation_id}`);
        } catch {
            toast.error('Could not open chat');
        } finally {
            setMessaging(false);
        }
    }

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

            {/* Weekly progress hero */}
            <section className="mt-5">
                <TodaysWorkoutCard
                    todayLabel={t('member.home.chip_progress')}
                    title={t('member.home.week_title')}
                    subtitle={t('member.home.week_stats', {
                        sessions: weekStats.sessions,
                        volume: Math.round(weekStats.volume).toLocaleString(),
                    })}
                    ctaLabel={t('member.home.log_workout')}
                    href={progress({ query: { log: '1' } })}
                    image="/images/track.png"
                    icon={NotebookPen}
                />
            </section>

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
            <div className="mt-4 flex scrollbar-none items-center justify-center gap-2 overflow-x-auto pb-1">
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

            {/* Your coach */}
            {coaches.length > 0 && (
                <section className="mt-4 space-y-2">
                    {coaches.map((coach) => (
                        <div
                            key={coach.id}
                            className="flex items-center gap-3 rounded-2xl border border-white/10 bg-[#1C1C1E] p-3"
                        >
                            <div className="flex size-10 items-center justify-center rounded-full bg-[#AFFA01]/15 text-sm font-bold text-[#AFFA01]">
                                {coach.name.slice(0, 1)}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                    {coach.name}
                                </p>
                                <p className="text-xs text-[#8E8E93]">
                                    Your coach
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="border-white/15 text-xs"
                                disabled={messaging}
                                onClick={() => messageCoach(coach.user_id)}
                            >
                                <MessageCircle className="size-3.5" />
                                Message
                            </Button>
                        </div>
                    ))}
                </section>
            )}

            {/* Community recap feed */}
            <section className="mt-6">
                <RecapFeed
                    initialRecaps={recaps}
                    initialNextCursor={recapsNextCursor}
                />
            </section>
        </MobileShell>
    );
}
