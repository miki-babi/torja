import { Link, usePage } from '@inertiajs/react';
import { Compass, Dumbbell, House, User, UtensilsCrossed } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { exercise, home, meal, profile } from '@/routes/member';
import { index as exploreIndex } from '@/routes/explore';

const TABS = [
    { label: 'home', href: home, paths: ['/'], icon: House },
    {
        label: 'exercise',
        href: exercise,
        paths: ['/exercise', '/today', '/playlists'],
        icon: Dumbbell,
    },
    { label: 'meal', href: meal, paths: ['/meal'], icon: UtensilsCrossed },
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
        <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-[#2E2E2E] bg-[#000000]/95 backdrop-blur-md">
            <div className="mx-auto flex h-16 max-w-md items-center justify-around px-2">
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
                            <Icon
                                className={cn(
                                    'size-5 transition-transform',
                                    active && 'scale-105',
                                )}
                            />
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
