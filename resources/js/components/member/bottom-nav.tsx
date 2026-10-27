import { Link, usePage } from '@inertiajs/react';
import { Compass, Dumbbell, House, User, UtensilsCrossed } from 'lucide-react';
import { cn } from '@/lib/utils';
import { exercise, home, meal, profile } from '@/routes/member';
import { index as programsIndex } from '@/routes/programs';

const TABS = [
    { label: 'home', href: home, paths: ['/home'], icon: House },
    {
        label: 'exercise',
        href: exercise,
        paths: ['/exercise', '/today', '/playlists'],
        icon: Dumbbell,
    },
    { label: 'meal', href: meal, paths: ['/meal'], icon: UtensilsCrossed },
    {
        label: 'programs',
        href: programsIndex,
        paths: ['/programs'],
        icon: Compass,
    },
    { label: 'profile', href: profile, paths: ['/profile'], icon: User },
] as const;

export function BottomNav() {
    const { url } = usePage();
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <nav className="fixed inset-x-0 bottom-0 z-50 border-t bg-background">
            <div className="mx-auto flex max-w-md">
                {TABS.map(({ label, href, paths, icon: Icon }) => {
                    const active = paths.some(
                        (path) => url === path || url.startsWith(`${path}/`),
                    );
                    return (
                        <Link
                            key={label}
                            href={href()}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-1 py-3 text-xs',
                                active
                                    ? 'text-lime-600 dark:text-lime-400'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <span
                                className={cn(
                                    'rounded-full px-4 py-1',
                                    active &&
                                        'bg-lime-200/70 dark:bg-lime-400/20',
                                )}
                            >
                                <Icon className="size-5" />
                            </span>
                            {label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
