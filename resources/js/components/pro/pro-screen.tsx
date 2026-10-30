import {
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Check,
    Crown,
    Dumbbell,
    Flame,
    Heart,
    Infinity as InfinityIcon,
    type LucideIcon,
    RefreshCw,
    Salad,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Trophy,
    X,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export const ICON_MAP: Record<string, LucideIcon> = {
    dumbbell: Dumbbell,
    salad: Salad,
    'calendar-days': CalendarDays,
    'refresh-cw': RefreshCw,
    infinity: InfinityIcon,
    sparkles: Sparkles,
    'badge-check': BadgeCheck,
    smartphone: Smartphone,
    'shield-check': ShieldCheck,
    zap: Zap,
    crown: Crown,
    heart: Heart,
    flame: Flame,
    trophy: Trophy,
};

export function iconFor(slug: string): LucideIcon {
    return ICON_MAP[slug] ?? Sparkles;
}

export type ProFeature = {
    icon: string;
    title: string;
    description: string;
    starter: boolean;
    pro: boolean;
};

export type ProTrustBadge = { icon: string; text: string };

export type ProCopy = {
    badge: string;
    headline: string;
    headline_accent: string;
    subheadline: string;
    starter_label: string;
    pro_label: string;
    features: ProFeature[];
    unlock_button: string;
    sheet_title: string;
    sheet_subtitle: string;
    price_label: string;
    price_pill: string;
    trust_badges: ProTrustBadge[];
    sheet_button: string;
    pro_title: string;
    pro_body: string;
    free_button: string;
    free_notice: string;
};

function Tick({ on, strong }: { on: boolean; strong: boolean }) {
    if (!on) {
        return (
            <span className="flex size-7 items-center justify-center rounded-full bg-[#2E2E2E] text-[#8E8E93]">
                <X className="size-3.5 stroke-[3]" />
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex size-7 items-center justify-center rounded-full',
                strong
                    ? 'bg-[#AFFA01] text-black'
                    : 'bg-[#AFFA01]/15 text-[#AFFA01]',
            )}
        >
            <Check className="size-3.5 stroke-[3]" />
        </span>
    );
}

/**
 * The prop-driven Pro upgrade screen. Used by /pro and, with `preview`,
 * by the admin editor's live phone frame.
 */
export function ProScreen({
    copy,
    isPro,
    onClose,
    onFree,
    onUnlock,
    preview = false,
}: {
    copy: ProCopy;
    isPro: boolean;
    onClose?: () => void;
    onFree?: () => void;
    onUnlock?: () => void;
    preview?: boolean;
}) {

    return (
        <div
            className={cn(
                'flex flex-col gap-3 text-[#F5F5F5]',
                preview
                    ? 'min-h-full'
                    : 'h-[calc(100dvh-3.5rem)] overflow-hidden',
            )}
        >
            <div className="space-y-1.5">
                {copy.badge !== '' && (
                    <span className="inline-block rounded-full bg-[#AFFA01]/15 px-2.5 py-1 text-[10px] font-bold tracking-wider text-[#AFFA01] uppercase">
                        {copy.badge}
                    </span>
                )}
                <div className="flex items-start justify-between gap-3">
                    <h1 className="text-2xl leading-tight font-extrabold tracking-tight">
                        {copy.headline}{' '}
                        <span className="text-[#AFFA01]">
                            {copy.headline_accent}
                        </span>
                    </h1>
                    {!preview && (
                        <button
                            type="button"
                            aria-label="Close"
                            onClick={onClose}
                            className="mt-1 flex size-9 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F] text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </div>
                <p className="text-sm text-[#8E8E93]">{copy.subheadline}</p>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-[#2E2E2E] bg-[#141414]">
                <div className="grid grid-cols-[1fr_3rem_3rem] items-center border-b border-[#2E2E2E] px-4 py-1.5 text-[10px] font-bold tracking-widest text-[#8E8E93] uppercase">
                    <span />
                    <span className="text-center">{copy.starter_label}</span>
                    <span className="text-center text-[#AFFA01]">
                        {copy.pro_label}
                    </span>
                </div>
                <div className="divide-y divide-[#2E2E2E]">
                    {copy.features.map((feature, index) => {
                        const Icon = iconFor(feature.icon);
                        return (
                            <div
                                key={index}
                                className="grid grid-cols-[1fr_3rem_3rem] items-center gap-2 px-4 py-2"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <Icon
                                        className="size-4.5 shrink-0 text-[#AFFA01]"
                                        strokeWidth={1.75}
                                    />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">
                                            {feature.title}
                                        </p>
                                        {feature.description !== '' && (
                                            <p className="text-xs leading-snug text-[#8E8E93]">
                                                {feature.description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex justify-center">
                                    <Tick on={feature.starter} strong={false} />
                                </div>
                                <div className="flex justify-center">
                                    <Tick on={feature.pro} strong />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {isPro ? (
                <div className="flex items-start gap-3 rounded-2xl border border-[#AFFA01]/30 bg-[#AFFA01]/10 p-4">
                    <BadgeCheck className="mt-0.5 size-5 shrink-0 text-[#AFFA01]" />
                    <div>
                        <p className="text-sm font-bold">{copy.pro_title}</p>
                        <p className="text-sm text-[#8E8E93]">{copy.pro_body}</p>
                    </div>
                </div>
            ) : (
                <div className="mt-auto space-y-2 pt-2">
                    <button
                        type="button"
                        onClick={onUnlock}
                        className="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-2.5 text-sm font-extrabold text-black shadow-md transition-transform active:scale-[0.98]"
                    >
                        {copy.unlock_button}
                        <ArrowRight className="size-4" />
                    </button>
                    {copy.free_button !== '' && (
                        <button
                            type="button"
                            onClick={onFree ?? onClose}
                            className="w-full rounded-full border border-[#2E2E2E] bg-[#1F1F1F] py-2.5 text-sm font-bold text-[#F5F5F5] transition-all hover:border-[#8E8E93]/60 hover:bg-[#2E2E2E] active:scale-[0.98]"
                        >
                            {copy.free_button}
                        </button>
                    )}
                </div>
            )}

        </div>
    );
}
