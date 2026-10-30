import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Crown,
    Gift,
    Languages,
    LogOut,
    Package,
    Palette,
    Send,
    Settings,
    Shield,
} from 'lucide-react';
import { useState } from 'react';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type { DiaryEntry, NutritionTargets } from '@/components/member/types';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { useLocalStorage } from '@/hooks/use-local-storage';
import { bmiCategory, calculateBmi } from '@/lib/body-metrics';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as appearanceEdit } from '@/routes/appearance';
import { update as localeUpdate } from '@/routes/locale';
import {
    disconnect as telegramDisconnect,
    link as telegramLink,
} from '@/routes/member/telegram';
import { edit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

const GOAL_LABELS: Record<string, string> = {
    lose_fat: 'Lose fat',
    build_muscle: 'Build muscle',
    recomp: 'Recomp',
    general: 'Stay healthy',
};

const ACTIVITY_LABELS: Record<string, string> = {
    sedentary: 'Sedentary',
    light: 'Lightly active',
    moderate: 'Moderately active',
    heavy: 'Very active',
};

const EXERCISE_DAY_LABELS: Record<string, string> = {
    none: 'No exercise',
    '1_2': '1–2 days / week',
    '3_5': '3–5 days / week',
    '6_7': '6–7 days / week',
};

function asNumber(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function asString(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}

function dateKeyFor(offset: number): string {
    const date = new Date();
    date.setDate(date.getDate() + offset);
    return date.toISOString().slice(0, 10);
}

export default function MemberProfile({
    account,
    telegram,
    goal,
    body,
    activity,
    pace,
    targets,
    planName,
    weightLogs,
    isPro,
    proUrl,
    proCard,
    ordersUrl,
    ambassador,
    streak,
    activityWeek,
}: {
    isPro: boolean;
    proUrl: string;
    ordersUrl: string;
    ambassador: {
        enabled: boolean;
        url: string;
        balance: number;
        commission_etb: number;
    };
    proCard: { title: string; body: string; button: string | null };
    account: {
        name: string | null;
        username: string | null;
        phone: string | null;
        email: string | null;
        member_since: string | null;
    };
    telegram: {
        connected: boolean;
        username: string | null;
        available: boolean;
    };
    goal: Record<string, unknown> | null;
    body: Record<string, unknown> | null;
    activity: Record<string, unknown> | null;
    pace: Record<string, unknown> | null;
    targets: NutritionTargets | null;
    planName: string | null;
    weightLogs: { id: number; logged_on: string; weight_kg: number }[];
    streak: number;
    activityWeek: { date: string; label: string; active: boolean }[];
}) {
    const getInitials = useInitials();
    const { t, locale, languages } = useTranslation();
    const [diaryByDate] = useLocalStorage<Record<string, DiaryEntry[]>>(
        'member:diary',
        {},
    );
    const [waterByDate] = useLocalStorage<Record<string, number>>(
        'member:water',
        {},
    );
    const [telegramConnected, setTelegramConnected] = useState(
        telegram.connected,
    );
    const [telegramBusy, setTelegramBusy] = useState(false);

    async function connectTelegram() {
        setTelegramBusy(true);
        try {
            const { url } = await memberApi<{ url: string }>(
                telegramLink().url,
                'POST',
            );
            window.open(url, '_blank', 'noopener');
        } catch {
            // bot not configured — leave the row as-is
        } finally {
            setTelegramBusy(false);
        }
    }

    async function disconnectTelegram() {
        setTelegramBusy(true);
        try {
            await memberApi(telegramDisconnect().url, 'DELETE');
            setTelegramConnected(false);
        } catch {
            // ignore — row stays connected
        } finally {
            setTelegramBusy(false);
        }
    }

    const weightKg = asNumber(body?.weight_kg);
    const heightCm = asNumber(body?.height_cm);
    const bmi =
        weightKg !== null && heightCm !== null
            ? calculateBmi(weightKg, heightCm)
            : null;

    const week = Array.from({ length: 7 }, (_, i) => {
        const offset = i - 6;
        const key = dateKeyFor(offset);
        const entries = diaryByDate[key] ?? [];
        return {
            key,
            label:
                offset === 0
                    ? 'Today'
                    : new Date(`${key}T12:00:00`).toLocaleDateString(
                          undefined,
                          { weekday: 'short' },
                      ),
            kcal: entries.reduce((sum, e) => sum + e.kcal, 0),
            meals: entries.length,
            water: waterByDate[key] ?? 0,
        };
    });
    const kcalTarget = targets?.kcal_target ?? 0;

    const stats = [
        { label: 'Age', value: asNumber(body?.age)?.toString() },
        { label: 'Sex', value: asString(body?.sex) },
        {
            label: 'Height',
            value: heightCm !== null ? `${heightCm} cm` : null,
        },
        {
            label: 'Weight',
            value: weightKg !== null ? `${weightKg} kg` : null,
        },
        {
            label: 'BMI',
            value:
                bmi !== null ? `${bmi.toFixed(1)} (${bmiCategory(bmi)})` : null,
        },
        {
            label: 'Activity',
            value:
                ACTIVITY_LABELS[asString(activity?.daily_activity) ?? ''] ??
                null,
        },
        {
            label: 'Exercise',
            value:
                EXERCISE_DAY_LABELS[asString(activity?.exercise_days) ?? ''] ??
                null,
        },
        {
            label: 'Goal',
            value: GOAL_LABELS[asString(goal?.primary_goal) ?? ''] ?? null,
        },
        {
            label: 'Target weight',
            value:
                asNumber(goal?.target_weight_kg) !== null
                    ? `${asNumber(goal?.target_weight_kg)} kg`
                    : null,
        },
        {
            label: 'Target date',
            value: asString(pace?.target_date),
        },
    ].filter((s) => s.value !== null && s.value !== '');

    return (
        <MobileShell>
            <Head title="Profile" />

            <div className="flex items-center gap-4 pt-2">
                <Avatar className="size-16 border-2 border-[#2E2E2E] shadow-md">
                    <AvatarFallback className="bg-[#1F1F1F] text-xl font-bold text-[#AFFA01]">
                        {getInitials(account.name ?? '?')}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <h1 className="truncate text-xl font-bold tracking-tight text-[#F5F5F5]">
                        {account.name ?? 'Member'}
                    </h1>
                    <p className="truncate text-xs font-medium text-[#8E8E93]">
                        {account.email ??
                            account.phone ??
                            `@${account.username}`}
                    </p>
                    <div className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]">
                        <span>🔥 {streak} days streak</span>
                    </div>
                    <div className="mt-1.5 flex items-center gap-1.5">
                        {activityWeek.map((day) => (
                            <div
                                key={day.date}
                                className="flex flex-col items-center gap-0.5"
                            >
                                <span
                                    className={cn(
                                        'flex size-4 items-center justify-center rounded-full',
                                        day.active
                                            ? 'bg-[#AFFA01]'
                                            : 'bg-[#2E2E2E]',
                                    )}
                                >
                                    {day.active && (
                                        <span className="size-1 rounded-full bg-black" />
                                    )}
                                </span>
                                <span className="text-[9px] font-medium text-[#8E8E93]">
                                    {day.label[0]}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="mt-6 space-y-4">
                <Card className="border-[#AFFA01]/30 bg-gradient-to-b from-[#1F1F1F] to-[#141414]">
                    <CardContent className="flex items-center gap-3 pt-4">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#AFFA01] text-black">
                            {isPro ? (
                                <BadgeCheck className="size-5" />
                            ) : (
                                <Crown className="size-5" />
                            )}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-bold text-[#F5F5F5]">
                                {proCard.title}
                            </p>
                            <p className="text-xs text-[#8E8E93]">
                                {proCard.body}
                            </p>
                        </div>
                    </CardContent>
                    {!isPro && proCard.button !== null && (
                        <div className="px-4 pb-4">
                            <Link
                                href={proUrl}
                                className="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-2.5 text-sm font-extrabold text-black shadow-md transition-transform active:scale-[0.98]"
                            >
                                {proCard.button}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    )}
                </Card>

                {ambassador.enabled && (
                    <Link href={ambassador.url} className="block">
                        <Card className="transition-colors hover:border-[#AFFA01]/40">
                            <CardContent className="flex items-center gap-3 pt-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#1F1F1F] text-[#AFFA01]">
                                    <Gift className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold text-[#F5F5F5]">
                                        {t('ambassador.card_title')}
                                    </p>
                                    <p className="text-xs text-[#8E8E93]">
                                        {t('ambassador.card_body', {
                                            amount: ambassador.commission_etb,
                                        })}
                                    </p>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-sm font-bold text-[#AFFA01]">
                                        {ambassador.balance.toLocaleString()}
                                    </p>
                                    <p className="text-[10px] text-[#8E8E93]">
                                        ETB
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Your stats</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No stats yet.
                            </p>
                        ) : (
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
                                {stats.map((s) => (
                                    <div key={s.label}>
                                        <dt className="text-xs text-muted-foreground">
                                            {s.label}
                                        </dt>
                                        <dd className="text-sm font-medium capitalize">
                                            {s.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </CardContent>
                </Card>

                {targets !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Daily targets
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Calories
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.kcal_target} kcal
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Protein
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.protein_g} g
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Carbs
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.carbs_g} g
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Fat
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.fat_g} g
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        BMR
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.bmr} kcal
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        TDEE
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {targets.tdee} kcal
                                    </dd>
                                </div>
                            </dl>
                            {planName !== null && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Plan: {planName}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-base">
                            Weekly report
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-7 items-end gap-1.5">
                            {week.map((day) => {
                                const pct =
                                    kcalTarget > 0
                                        ? Math.min(
                                              100,
                                              (day.kcal / kcalTarget) * 100,
                                          )
                                        : 0;
                                return (
                                    <div
                                        key={day.key}
                                        className="flex flex-col items-center gap-1"
                                    >
                                        <span className="text-[10px] text-muted-foreground">
                                            {Math.round(day.kcal)}
                                        </span>
                                        <div className="flex h-20 w-full items-end overflow-hidden rounded-sm bg-muted">
                                            <div
                                                className="w-full rounded-sm bg-lime-400"
                                                style={{
                                                    height: `${Math.max(pct, day.kcal > 0 ? 4 : 0)}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="text-[10px] text-muted-foreground">
                                            {day.label}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="mt-3 grid grid-cols-3 gap-2 border-t pt-3 text-center">
                            <div>
                                <p className="text-sm font-medium">
                                    {week.reduce((s, d) => s + d.meals, 0)}
                                </p>
                                <p className="text-[10px] text-muted-foreground">
                                    meals logged
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium">
                                    {Math.round(
                                        week.reduce((s, d) => s + d.kcal, 0),
                                    )}
                                </p>
                                <p className="text-[10px] text-muted-foreground">
                                    kcal this week
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium">
                                    {(
                                        week.reduce((s, d) => s + d.water, 0) /
                                        1000
                                    ).toFixed(1)}
                                    L
                                </p>
                                <p className="text-[10px] text-muted-foreground">
                                    water this week
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {weightLogs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Weight history
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="divide-y">
                                {weightLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="text-muted-foreground">
                                            {log.logged_on}
                                        </span>
                                        <span className="font-medium">
                                            {log.weight_kg} kg
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Settings</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y p-0">
                        <Link
                            href={edit()}
                            className="flex items-center gap-3 px-6 py-3 text-sm hover:bg-accent"
                        >
                            <Settings className="size-4 text-muted-foreground" />
                            Profile settings
                        </Link>
                        <Link
                            href={securityEdit()}
                            className="flex items-center gap-3 px-6 py-3 text-sm hover:bg-accent"
                        >
                            <Shield className="size-4 text-muted-foreground" />
                            Security
                        </Link>
                        <Link
                            href={ordersUrl}
                            className="flex items-center gap-3 px-6 py-3 text-sm hover:bg-accent"
                        >
                            <Package className="size-4 text-muted-foreground" />
                            My orders
                        </Link>
                        <Link
                            href={appearanceEdit()}
                            className="flex items-center gap-3 px-6 py-3 text-sm hover:bg-accent"
                        >
                            <Palette className="size-4 text-muted-foreground" />
                            Appearance
                        </Link>
                        <div className="flex items-center gap-3 px-6 py-3">
                            <Languages className="size-4 text-muted-foreground" />
                            <span className="flex-1 text-sm">
                                {t('settings.language')}
                            </span>
                            <Select
                                value={locale}
                                onValueChange={(value) =>
                                    router.patch(
                                        localeUpdate().url,
                                        { locale: value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger className="h-8 w-auto min-w-28 gap-1.5 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {languages.map((language) => (
                                        <SelectItem
                                            key={language.code}
                                            value={language.code}
                                        >
                                            {language.native_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {telegram.available && (
                            <button
                                type="button"
                                onClick={
                                    telegramConnected
                                        ? disconnectTelegram
                                        : connectTelegram
                                }
                                disabled={telegramBusy}
                                className="flex w-full items-center gap-3 px-6 py-3 text-left text-sm hover:bg-accent disabled:opacity-50"
                            >
                                <Send className="size-4 text-muted-foreground" />
                                {telegramConnected
                                    ? `Telegram connected${telegram.username ? ` (@${telegram.username})` : ''} — disconnect`
                                    : 'Connect Telegram reminders'}
                            </button>
                        )}
                        <Link
                            href={logout()}
                            method="post"
                            as="button"
                            className="flex w-full items-center gap-3 px-6 py-3 text-left text-sm text-destructive hover:bg-accent"
                        >
                            <LogOut className="size-4" />
                            Log out
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </MobileShell>
    );
}
