import { useEffect, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import {
    reportTelegramDebug,
    telegramInitData,
    telegramSignIn,
} from '@/lib/telegram';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const [pending, setPending] = useState(() => telegramInitData() !== null);
    const attemptedRef = useRef(false);

    useEffect(() => {
        if (!pending || attemptedRef.current) {
            return;
        }

        attemptedRef.current = true;

        const payload = telegramInitData();

        if (payload === null) {
            reportTelegramDebug('no-init-data', {
                hash: window.location.hash.slice(0, 200),
                sdk: window.Telegram !== undefined,
            });
            setPending(false);

            return;
        }

        window.Telegram?.WebApp?.ready?.();
        window.Telegram?.WebApp?.expand?.();

        reportTelegramDebug('init-data-found', {
            length: payload.initData.length,
            source: payload.source,
        });

        telegramSignIn(payload.initData)
            .then((res) => {
                window.location.href = res.redirect;
            })
            .catch((error: { status?: number }) => {
                reportTelegramDebug('webapp-failed', {
                    status: error.status ?? 'network-error',
                });
                setPending(false);
            });
    }, [pending]);

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {pending ? (
                <div className="flex items-center justify-center py-16">
                    <Spinner />
                </div>
            ) : (
                children
            )}
        </AuthLayoutTemplate>
    );
}
