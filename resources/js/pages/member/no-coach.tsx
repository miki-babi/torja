import { Head } from '@inertiajs/react';
import { Dumbbell } from 'lucide-react';
import { MobileShell } from '@/components/member/mobile-shell';
import { useTranslation } from '@/lib/i18n';

export default function NoCoach() {
    const { t } = useTranslation();

    return (
        <MobileShell hideNav hideBrand>
            <Head title={t('member.no_coach.title')} />

            <div className="flex min-h-[70vh] flex-col items-center justify-center text-center">
                <div className="flex size-16 items-center justify-center rounded-full bg-[#AFFA01]/15">
                    <Dumbbell className="size-7 text-[#AFFA01]" />
                </div>
                <h1 className="mt-5 text-xl font-bold tracking-tight text-[#F5F5F5]">
                    {t('member.no_coach.title')}
                </h1>
                <p className="mt-2 max-w-xs text-sm leading-relaxed text-[#8E8E93]">
                    {t('member.no_coach.body')}
                </p>
            </div>
        </MobileShell>
    );
}
