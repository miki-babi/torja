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
 * Ad banner carousel for the member home screen. Slides are 80% wide and
 * snap to the center so the previous and next cards peek at the screen
 * edges — a visual cue that the row scrolls. A timer advances the center
 * slide (wrapping around) unless the user recently interacted or prefers
 * reduced motion. Each slide links in-app (`/...`) or externally
 * (`http...`, new tab), and a click is beaconed as `home_banner.clicked`.
 * Renders nothing when the admin hasn't configured any banners.
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

            scrollToSlide(el, (centerIndex(el) + 1) % banners.length);
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
        setActive(centerIndex(el));
    }

    function goTo(index: number) {
        pause();
        const el = scroller.current;
        if (el !== null) {
            scrollToSlide(el, index);
        }
    }

    return (
        <div>
            <div
                ref={scroller}
                onScroll={handleScroll}
                onPointerDown={pause}
                onTouchStart={pause}
                className="relative -mx-4 flex snap-x snap-mandatory scrollbar-none gap-3 overflow-x-auto"
            >
                {banners.map((banner, index) => (
                    <div
                        key={`${banner.image}-${index}`}
                        className={cn(
                            'w-[80%] shrink-0 snap-center',
                            index === 0 && 'ml-[10%]',
                            index === banners.length - 1 && 'mr-[10%]',
                        )}
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
                <div className="mt-2 flex justify-center gap-1.5">
                    {banners.map((banner, index) => (
                        <button
                            key={`${banner.image}-dot-${index}`}
                            type="button"
                            aria-label={`${t('member.home.sponsored')} ${index + 1}`}
                            onClick={() => goTo(index)}
                            className={cn(
                                'size-1.5 rounded-full transition-colors',
                                index === active
                                    ? 'bg-[#AFFA01]'
                                    : 'bg-[#3A3A3A]',
                            )}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

/** Index of the slide whose center is nearest the scrollport center. */
function centerIndex(el: HTMLDivElement): number {
    const center = el.scrollLeft + el.clientWidth / 2;
    let best = 0;
    let bestDistance = Number.POSITIVE_INFINITY;

    for (const [index, slide] of Array.from(el.children).entries()) {
        const item = slide as HTMLElement;
        const distance = Math.abs(
            item.offsetLeft + item.offsetWidth / 2 - center,
        );
        if (distance < bestDistance) {
            bestDistance = distance;
            best = index;
        }
    }

    return best;
}

/** Smooth-scroll so the given slide sits centered in the scrollport. */
function scrollToSlide(el: HTMLDivElement, index: number): void {
    const slide = el.children[index] as HTMLElement | undefined;
    if (slide === undefined) {
        return;
    }
    el.scrollTo({
        left: slide.offsetLeft - (el.clientWidth - slide.offsetWidth) / 2,
        behavior: 'smooth',
    });
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
        <span className="relative block aspect-[2/1] w-full overflow-hidden rounded-[24px] border border-[#2E2E2E] bg-black">
            <img
                src={banner.image}
                alt={label}
                draggable={false}
                className="absolute inset-0 size-full object-cover"
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
