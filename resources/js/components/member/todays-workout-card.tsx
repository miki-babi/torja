import { Link } from '@inertiajs/react';
import { Play } from 'lucide-react';

export function TodaysWorkoutCard({
    title,
    subtitle,
    href,
    ctaLabel = 'Start Workout',
    todayLabel = 'TODAY',
}: {
    title: string;
    subtitle: string;
    href: string | { url: string };
    ctaLabel?: string;
    todayLabel?: string;
}) {
    const targetUrl = typeof href === 'string' ? href : href.url;
    return (
        <div className="relative overflow-hidden rounded-[24px] border border-[#2E2E2E] shadow-[0_12px_24px_rgba(175,250,1,0.12)]">
            <div className="relative h-[280px] w-full">
                {/* Background Image */}
                <img
                    src="/images/community.png"
                    alt=""
                    className="absolute inset-0 size-full object-cover object-center"
                />

                {/* Rich Dark Gradient Overlay */}
                <div className="absolute inset-0 bg-gradient-to-b from-black/25 via-black/50 to-black/95" />

                {/* Content Container */}
                <div className="relative flex size-full flex-col justify-between p-5">
                    {/* Top Pill Badge */}
                    <div className="flex items-center">
                        <span className="inline-flex items-center rounded-full border border-white/20 bg-black/50 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase backdrop-blur-md">
                            {todayLabel}
                        </span>
                    </div>

                    {/* Bottom Info and Action */}
                    <div className="space-y-3">
                        <div>
                            <h2 className="text-2xl font-bold leading-tight tracking-tight text-white line-clamp-2">
                                {title}
                            </h2>
                            <p className="mt-1 text-sm font-medium text-white/80">
                                {subtitle}
                            </p>
                        </div>

                        {/* Fit Green Play CTA */}
                        <Link
                            href={targetUrl}
                            className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black shadow-[0_4px_16px_rgba(175,250,1,0.3)] transition-all hover:brightness-105 active:scale-[0.98]"
                        >
                            <Play className="size-5 fill-current" />
                            <span className="text-sm font-bold tracking-wide uppercase">
                                {ctaLabel}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
