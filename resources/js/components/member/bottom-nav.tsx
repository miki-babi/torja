import { Link, usePage } from '@inertiajs/react';
import { Dumbbell, House, TrendingUp, Trophy, User } from 'lucide-react';
import { cn } from '@/lib/utils';
import { exercise, home, profile, progress, ranks } from '@/routes/member';

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
        label: 'ranks',
        href: ranks,
        paths: ['/member/ranks'],
        icon: Trophy,
    },
    { label: 'profile', href: profile, paths: ['/profile'], icon: User },
] as const;

export function BottomNav() {
    const { url } = usePage();
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    const activeIndex = TABS.findIndex(({ paths }) =>
        paths.some((path) => url === path || url.startsWith(`${path}/`)),
    );

    return (
        <nav className="fixed inset-x-3 bottom-4 z-50 mx-auto max-w-md rounded-full border border-[#2E2E2E] bg-[#101010]/95 shadow-lg backdrop-blur-md">
            <div className="relative flex h-16 items-center px-2">
                {activeIndex >= 0 && (
                    <span
                        aria-hidden
                        className="pointer-events-none absolute inset-x-2 inset-y-0 flex items-center"
                    >
                        <span
                            className="transition-transform duration-300 ease-out"
                            style={{
                                width: `${100 / TABS.length}%`,
                                transform: `translateX(${activeIndex * 100}%)`,
                            }}
                        >
                            <span className="mx-auto block size-12 rounded-full border border-[#3a3a3a] bg-black" />
                        </span>
                    </span>
                )}
                {TABS.map(({ label, href, paths, icon: Icon }) => {
                    const active = paths.some(
                        (path) => url === path || url.startsWith(`${path}/`),
                    );
                    return (
                        <Link
                            key={label}
                            href={href()}
                            aria-label={label}
                            className={cn(
                                'relative flex flex-1 items-center justify-center transition-colors duration-300',
                                active
                                    ? 'text-[#AFFA01]'
                                    : 'text-[#666666] hover:text-[#999999]',
                            )}
                        >
                            <Icon className="size-6" />
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
