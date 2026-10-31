import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { track } from '@/lib/tracking';
import { cn } from '@/lib/utils';

export type HomeBanner = {
    image: string;
    url: string;
    cta: string;
    external: boolean;
};

const AUTOPLAY_MS = 5000;
const RESUME_AFTER_INTERACTION_MS = 12000;

/**
 * Ad banner carousel for the member home screen. Native scroll-snap
 * handles swiping; a timer advances to the next slide (wrapping around)
 * unless the user recently interacted or prefers reduced motion. Each
 * slide links in-app (`/...`) or externally (`http...`, new tab), and a
 * click is beaconed as `home_banner.clicked`. Renders nothing when the
 * admin hasn't configured any banners.
 */
export function BannerCarousel({ banners }: { banners: HomeBanner[] }) {
    const { t } = useTranslation();
    const scroller = useRef<HTMLDivElement>(null);
    const pausedUntil = useRef(0);
    const [active, setActive] = useState(0);
    const multiple = banners.length > 1;

    useEffect(() => {
        if (
            !multiple ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const id = setInterval(() => {
            const el = scroller.current;
            if (
                el === null ||
                document.hidden ||
                Date.now() < pausedUntil.current ||
                el.clientWidth === 0
            ) {
                return;
            }

            const next =
                (Math.round(el.scrollLeft / el.clientWidth) + 1) %
                banners.length;
            el.scrollTo({ left: next * el.clientWidth, behavior: 'smooth' });
        }, AUTOPLAY_MS);

        return () => clearInterval(id);
    }, [multiple, banners.length]);

    if (banners.length === 0) {
        return null;
    }

    function pause() {
        pausedUntil.current = Date.now() + RESUME_AFTER_INTERACTION_MS;
    }

    function handleScroll() {
        const el = scroller.current;
        if (el === null || el.clientWidth === 0) {
            return;
        }
        setActive(Math.round(el.scrollLeft / el.clientWidth));
    }

    function goTo(index: number) {
        pause();
        const el = scroller.current;
        el?.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' });
    }

    return (
        <div className="relative overflow-hidden rounded-[24px] border border-[#2E2E2E]">
            <div
                ref={scroller}
                onScroll={handleScroll}
                onPointerDown={pause}
                onTouchStart={pause}
                className="flex snap-x snap-mandatory scrollbar-none overflow-x-auto"
            >
                {banners.map((banner, index) => (
                    <div
                        key={`${banner.image}-${index}`}
                        className="w-full shrink-0 snap-center bg-black"
                    >
                        <BannerSlide
                            banner={banner}
                            index={index}
                            label={t('member.home.sponsored')}
                            fallbackCta={t('member.home.learn_more')}
                        />
                    </div>
                ))}
            </div>

            {multiple && (
                <div className="pointer-events-none absolute inset-x-0 bottom-2.5 flex justify-center gap-1.5">
                    {banners.map((banner, index) => (
                        <button
                            key={`${banner.image}-dot-${index}`}
                            type="button"
                            aria-label={`${t('member.home.sponsored')} ${index + 1}`}
                            onClick={() => goTo(index)}
                            className={cn(
                                'pointer-events-auto size-1.5 rounded-full transition-colors',
                                index === active
                                    ? 'bg-[#AFFA01]'
                                    : 'bg-white/40',
                            )}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function BannerSlide({
    banner,
    index,
    label,
    fallbackCta,
}: {
    banner: HomeBanner;
    index: number;
    label: string;
    fallbackCta: string;
}) {
    function clicked() {
        track('home_banner.clicked', { index, url: banner.url });
    }

    const content = (
        <span className="relative block aspect-[32/7] w-full">
            <img
                src={banner.image}
                alt={label}
                draggable={false}
                className="absolute inset-0 size-full object-contain"
            />
            <span className="absolute right-3 bottom-2.5 rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-3 py-1.5 text-xs font-bold tracking-wide text-black uppercase shadow-[0_2px_10px_rgba(175,250,1,0.3)]">
                {banner.cta !== '' ? banner.cta : fallbackCta}
            </span>
        </span>
    );

    if (banner.external) {
        return (
            <a
                href={banner.url}
                target="_blank"
                rel="noopener noreferrer sponsored"
                onClick={clicked}
                className="block"
            >
                {content}
            </a>
        );
    }

    return (
        <Link href={banner.url} onClick={clicked} className="block">
            {content}
        </Link>
    );
}
