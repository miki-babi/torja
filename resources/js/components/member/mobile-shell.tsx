import { router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { BottomNav } from '@/components/member/bottom-nav';
import { SessionFab } from '@/components/member/session-fab';
import { NudgeBanner } from '@/components/nudge-banner';
import {
    telegramInitData,
    telegramSignIn,
    telegramStartParam,
} from '@/lib/telegram';
import { cn } from '@/lib/utils';

let startParamReported = false;

/**
 * Report a Mini App `startapp` start param to the server once per app
 * load so an already-authenticated member opening an invite link gets
 * their referrer bound — the same binding guests get during webapp
 * sign-in. Only `ref_*` params (ambassador invites) are reported; the
 * response's redirect is intentionally ignored since the member is
 * already in-app. `ex_*` params (exercises shared via inline mode)
 * navigate straight to the exercise page instead.
 */
function useTelegramStartParam() {
    useEffect(() => {
        if (startParamReported) {
            return;
        }

        startParamReported = true;

        const payload = telegramInitData();

        if (payload === null) {
            return;
        }

        const param = telegramStartParam(payload.initData);

        if (param === null) {
            return;
        }

        if (/^ex_[\w-]+$/.test(param)) {
            router.visit(`/exercise/${param.slice(3)}`);

            return;
        }

        if (!param.startsWith('ref_')) {
            return;
        }

        telegramSignIn(payload.initData).catch(() => {});
    }, []);
}

export function MobileShell({
    children,
    hideNav = false,
}: {
    children: ReactNode;
    hideNav?: boolean;
}) {
    const { auth } = usePage().props;

    useTelegramStartParam();

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
            <SessionFab />
            {!hideNav && <BottomNav />}
        </div>
    );
}
