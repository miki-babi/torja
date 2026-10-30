import { useEffect, useState } from 'react';
import { memberApi } from '@/components/member/member-api';
import { Spinner } from '@/components/ui/spinner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { telegramInitData } from '@/lib/telegram';
import { webapp } from '@/routes/telegram/login';

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

    useEffect(() => {
        if (!pending) {
            return;
        }

        const initData = telegramInitData();

        if (initData === null) {
            setPending(false);

            return;
        }

        memberApi<{ redirect: string }>(webapp().url, 'POST', {
            init_data: initData,
        })
            .then((res) => {
                window.location.href = res.redirect;
            })
            .catch(() => setPending(false));
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
