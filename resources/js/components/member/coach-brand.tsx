import { usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';

/**
 * Slim brand bar shown at the top of the member app when the member is a
 * client — the coach's display name and avatar stand in for app branding
 * so the mini app reads as the coach's own.
 */
export function CoachBrand() {
    const { coach } = usePage().props;
    const { t } = useTranslation();
    const initials = useInitials();

    if (!coach) {
        return null;
    }

    return (
        <div className="mb-4 flex items-center gap-2.5">
            <Avatar className="size-8 border border-[#2E2E2E]">
                <AvatarImage src={coach.avatar ?? undefined} alt={coach.name} />
                <AvatarFallback className="bg-[#1C1C1E] text-[10px] font-bold text-[#AFFA01]">
                    {initials(coach.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate text-sm leading-tight font-semibold text-[#F5F5F5]">
                    {coach.name}
                </p>
                <p className="text-[10px] tracking-wide text-[#8E8E93] uppercase">
                    {t('member.coach.your_coach')}
                </p>
            </div>
        </div>
    );
}
