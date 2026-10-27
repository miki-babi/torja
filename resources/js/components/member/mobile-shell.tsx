import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BottomNav } from '@/components/member/bottom-nav';
import { NudgeBanner } from '@/components/nudge-banner';
import { cn } from '@/lib/utils';

export function MobileShell({
    children,
    hideNav = false,
}: {
    children: ReactNode;
    hideNav?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-background">
            <div
                className={cn(
                    'mx-auto w-full max-w-md px-4 pt-6',
                    auth.user && !hideNav ? 'pb-28' : 'pb-8',
                )}
            >
                <NudgeBanner />
                {children}
            </div>
            {!hideNav && <BottomNav />}
        </div>
    );
}
