import { Link, usePage } from '@inertiajs/react';
import { Compass, Dumbbell, House, TrendingUp, User } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { exercise, home, profile, progress } from '@/routes/member';
import { index as exploreIndex } from '@/routes/explore';

const TABS = [
    { label: 'home', href: home, paths: ['/'], icon: House },
    {
        label: 'exercise',
        href: exercise,
        paths: ['/exercise', '/today', '/playlists'],
        icon: Dumbbell,
    },
    {
        label: 'progress',
        href: progress,
        paths: ['/progress'],
        icon: TrendingUp,
    },
    {
        label: 'explore',
        href: exploreIndex,
        paths: ['/explore'],
        icon: Compass,
    },
    { label: 'profile', href: profile, paths: ['/profile'], icon: User },
] as const;

export function BottomNav() {
    const { url } = usePage();
    const { auth } = usePage().props;
    const { t } = useTranslation();

    if (!auth.user) {
        return null;
    }

    return (
        <nav className="fixed inset-x-3 bottom-4 z-50 mx-auto max-w-md rounded-full border border-[#2E2E2E] bg-[#101010]/95 shadow-lg backdrop-blur-md">
            <div className="flex h-16 items-center justify-around px-2">
                {TABS.map(({ label, href, paths, icon: Icon }) => {
                    const active = paths.some(
                        (path) => url === path || url.startsWith(`${path}/`),
                    );
                    return (
                        <Link
                            key={label}
                            href={href()}
                            className={cn(
                                'flex flex-1 flex-col items-center justify-center gap-1 py-1 transition-colors',
                                active
                                    ? 'text-[#AFFA01]'
                                    : 'text-[#666666] hover:text-[#999999]',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-9 items-center justify-center rounded-full transition-colors',
                                    active &&
                                        'border border-[#3a3a3a] bg-black',
                                )}
                            >
                                <Icon className="size-5" />
                            </span>
                            <span
                                className={cn(
                                    'text-[10px] tracking-wide uppercase',
                                    active ? 'font-bold' : 'font-medium',
                                )}
                            >
                                {t(`nav.${label}`)}
                            </span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
