import { useEffect, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { telegramInitData, telegramSignIn } from '@/lib/telegram';

export default function AuthLayout({
    title = '',
    description = '',
    skipTelegram = false,
    children,
}: {
    title?: string;
    description?: string;
    skipTelegram?: boolean;
    children: React.ReactNode;
}) {
    const [pending, setPending] = useState(
        () => !skipTelegram && telegramInitData() !== null,
    );
    const attemptedRef = useRef(false);

    useEffect(() => {
        if (!pending || attemptedRef.current) {
            return;
        }

        attemptedRef.current = true;

        const payload = telegramInitData();

        if (payload === null) {
            setPending(false);

            return;
        }

        window.Telegram?.WebApp?.ready?.();
        window.Telegram?.WebApp?.expand?.();

        telegramSignIn(payload.initData)
            .then((res) => {
                window.location.href = res.redirect;
            })
            .catch(() => {
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
