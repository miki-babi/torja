import { Head, router } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Check,
    Copy,
    Gift,
    Share2,
    Wallet,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store as storePayout } from '@/routes/member/ambassador/payouts';

type LedgerEntry = {
    id: string;
    kind: 'credit' | 'debit';
    amount: number;
    status: string;
    label: string;
    date: string;
};

type ReferralRow = {
    name: string;
    joined: string;
    is_pro: boolean;
    commission_status: string | null;
};

type PayoutRow = {
    id: number;
    amount: number;
    method: string;
    status: string;
    date: string;
};

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-400/15 text-amber-400',
    approved: 'bg-sky-400/15 text-sky-400',
    paid: 'bg-[#AFFA01]/15 text-[#AFFA01]',
    void: 'bg-red-400/15 text-red-400',
    requested: 'bg-amber-400/15 text-amber-400',
    rejected: 'bg-red-400/15 text-red-400',
};

const METHODS = ['telebirr', 'mpesa', 'cbe', 'bank'] as const;

export default function MemberAmbassador({
    enabled,
    inviteUrl,
    commissionEtb,
    minPayoutEtb,
    wallet,
    stats,
    ledger,
    referrals,
    payouts,
    hasOpenPayout,
}: {
    enabled: boolean;
    inviteUrl: string | null;
    commissionEtb: number;
    minPayoutEtb: number;
    wallet: { available: number; pending: number; paid: number };
    stats: { referrals: number; conversions: number };
    ledger: LedgerEntry[];
    referrals: ReferralRow[];
    payouts: PayoutRow[];
    hasOpenPayout: boolean;
}) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [method, setMethod] = useState<string>('telebirr');
    const [account, setAccount] = useState('');
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const canRequest =
        enabled && !hasOpenPayout && wallet.available >= minPayoutEtb;

    async function copyLink() {
        if (inviteUrl === null) {
            return;
        }

        try {
            await navigator.clipboard.writeText(inviteUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // clipboard unavailable — the URL stays visible for manual copy
        }
    }

    async function shareLink() {
        if (inviteUrl === null) {
            return;
        }

        if (typeof navigator.share === 'function') {
            try {
                await navigator.share({
                    title: t('ambassador.share_title'),
                    text: t('ambassador.share_text'),
                    url: inviteUrl,
                });

                return;
            } catch {
                return;
            }
        }

        await copyLink();
    }

    function requestPayout(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSubmitting(true);

        router.post(
            storePayout().url,
            { method, account, note: note === '' ? undefined : note },
            {
                onFinish: () => {
                    setSubmitting(false);
                    setDialogOpen(false);
                    setAccount('');
                    setNote('');
                },
            },
        );
    }

    return (
        <MobileShell>
            <Head title={t('ambassador.title')} />

            <h1 className="pt-2 text-xl font-bold tracking-tight text-[#F5F5F5]">
                {t('ambassador.title')}
            </h1>
            <p className="mt-1 text-xs text-[#8E8E93]">
                {t('ambassador.hero_body', { amount: commissionEtb })}
            </p>

            {/* Invite link */}
            <Card className="mt-5 border-[#AFFA01]/30 bg-gradient-to-b from-[#1F1F1F] to-[#141414]">
                <CardContent className="pt-4">
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#AFFA01] text-black">
                            <Gift className="size-5" />
                        </span>
                        <p className="min-w-0 flex-1 truncate text-sm font-medium text-[#F5F5F5]">
                            {inviteUrl}
                        </p>
                    </div>
                    <div className="mt-4 flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={copyLink}
                        >
                            {copied ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                            {copied
                                ? t('ambassador.copied')
                                : t('ambassador.copy_link')}
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            onClick={shareLink}
                        >
                            <Share2 className="size-4" />
                            {t('ambassador.share')}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Wallet */}
            <Card className="mt-4">
                <CardContent className="pt-4">
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#1F1F1F] text-[#AFFA01]">
                            <Wallet className="size-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-[#8E8E93]">
                                {t('ambassador.available')}
                            </p>
                            <p className="text-2xl font-bold text-[#F5F5F5]">
                                {wallet.available.toLocaleString()}{' '}
                                <span className="text-sm font-semibold text-[#8E8E93]">
                                    ETB
                                </span>
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2 border-t border-[#2E2E2E] pt-3 text-center">
                        <div>
                            <p className="text-sm font-semibold text-[#F5F5F5]">
                                {wallet.pending.toLocaleString()} ETB
                            </p>
                            <p className="text-[10px] text-[#8E8E93]">
                                {t('ambassador.pending')}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-[#F5F5F5]">
                                {wallet.paid.toLocaleString()} ETB
                            </p>
                            <p className="text-[10px] text-[#8E8E93]">
                                {t('ambassador.paid_total')}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4">
                        {hasOpenPayout ? (
                            <p className="rounded-xl border border-amber-400/30 bg-amber-400/10 px-3 py-2.5 text-center text-xs font-semibold text-amber-400">
                                {t('ambassador.payout_in_review')}
                            </p>
                        ) : (
                            <Button
                                type="button"
                                className="w-full"
                                disabled={!canRequest}
                                onClick={() => setDialogOpen(true)}
                            >
                                {t('ambassador.request_payout')}
                            </Button>
                        )}
                        {!hasOpenPayout && wallet.available < minPayoutEtb && (
                            <p className="mt-2 text-center text-[10px] text-[#8E8E93]">
                                {t('ambassador.payout_min_hint', {
                                    amount: minPayoutEtb,
                                })}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Stats */}
            <div className="mt-4 grid grid-cols-2 gap-3">
                <Card>
                    <CardContent className="pt-4 text-center">
                        <p className="text-xl font-bold text-[#F5F5F5]">
                            {stats.referrals}
                        </p>
                        <p className="text-[10px] text-[#8E8E93]">
                            {t('ambassador.stat_invited')}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4 text-center">
                        <p className="text-xl font-bold text-[#AFFA01]">
                            {stats.conversions}
                        </p>
                        <p className="text-[10px] text-[#8E8E93]">
                            {t('ambassador.stat_pro')}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Ledger */}
            <Card className="mt-4">
                <CardContent className="pt-4">
                    <h2 className="text-sm font-bold text-[#F5F5F5]">
                        {t('ambassador.ledger_title')}
                    </h2>
                    {ledger.length === 0 ? (
                        <p className="py-6 text-center text-xs text-[#8E8E93]">
                            {t('ambassador.ledger_empty')}
                        </p>
                    ) : (
                        <div className="mt-2 divide-y divide-[#2E2E2E]">
                            {ledger.map((entry) => (
                                <div
                                    key={entry.id}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                            entry.kind === 'credit'
                                                ? 'bg-[#AFFA01]/15 text-[#AFFA01]'
                                                : 'bg-[#2E2E2E] text-[#8E8E93]',
                                        )}
                                    >
                                        {entry.kind === 'credit' ? (
                                            <ArrowDownLeft className="size-4" />
                                        ) : (
                                            <ArrowUpRight className="size-4" />
                                        )}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-[#F5F5F5]">
                                            {entry.kind === 'credit'
                                                ? t('ambassador.credit_label', {
                                                      name: entry.label,
                                                  })
                                                : `${t('ambassador.debit_label')} · ${t(`ambassador.method.${entry.label}`)}`}
                                        </p>
                                        <p className="text-[10px] text-[#8E8E93]">
                                            {entry.date}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        <span
                                            className={cn(
                                                'text-sm font-bold',
                                                entry.kind === 'credit'
                                                    ? 'text-[#AFFA01]'
                                                    : 'text-[#8E8E93]',
                                            )}
                                        >
                                            {entry.kind === 'credit'
                                                ? '+'
                                                : '-'}
                                            {entry.amount.toLocaleString()}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                                                STATUS_STYLES[entry.status] ??
                                                    'bg-[#2E2E2E] text-[#8E8E93]',
                                            )}
                                        >
                                            {t(
                                                `ambassador.status.${entry.status}`,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Referrals */}
            <Card className="mt-4">
                <CardContent className="pt-4">
                    <h2 className="text-sm font-bold text-[#F5F5F5]">
                        {t('ambassador.referrals_title')}
                    </h2>
                    {referrals.length === 0 ? (
                        <p className="py-6 text-center text-xs text-[#8E8E93]">
                            {t('ambassador.referrals_empty')}
                        </p>
                    ) : (
                        <div className="mt-2 divide-y divide-[#2E2E2E]">
                            {referrals.map((referral, index) => (
                                <div
                                    key={index}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-[#F5F5F5]">
                                            {referral.name}
                                        </p>
                                        <p className="text-[10px] text-[#8E8E93]">
                                            {t('ambassador.joined')}{' '}
                                            {referral.joined}
                                        </p>
                                    </div>
                                    {referral.commission_status !== null ? (
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                                                STATUS_STYLES[
                                                    referral.commission_status
                                                ] ??
                                                    'bg-[#2E2E2E] text-[#8E8E93]',
                                            )}
                                        >
                                            {t(
                                                `ambassador.status.${referral.commission_status}`,
                                            )}
                                        </span>
                                    ) : (
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                                                referral.is_pro
                                                    ? 'bg-[#AFFA01]/15 text-[#AFFA01]'
                                                    : 'bg-[#2E2E2E] text-[#8E8E93]',
                                            )}
                                        >
                                            {referral.is_pro
                                                ? t('ambassador.pro_badge')
                                                : t('ambassador.free_badge')}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Payout history */}
            {payouts.length > 0 && (
                <Card className="mt-4">
                    <CardContent className="pt-4">
                        <h2 className="text-sm font-bold text-[#F5F5F5]">
                            {t('ambassador.payouts_title')}
                        </h2>
                        <div className="mt-2 divide-y divide-[#2E2E2E]">
                            {payouts.map((payout) => (
                                <div
                                    key={payout.id}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-[#F5F5F5]">
                                            {payout.amount.toLocaleString()} ETB
                                            ·{' '}
                                            {t(
                                                `ambassador.method.${payout.method}`,
                                            )}
                                        </p>
                                        <p className="text-[10px] text-[#8E8E93]">
                                            {payout.date}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                                            STATUS_STYLES[payout.status] ??
                                                'bg-[#2E2E2E] text-[#8E8E93]',
                                        )}
                                    >
                                        {t(
                                            `ambassador.status.${payout.status}`,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Payout dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('ambassador.payout_dialog_title')}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={requestPayout} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="payout-method">
                                {t('ambassador.payout_method')}
                            </Label>
                            <Select value={method} onValueChange={setMethod}>
                                <SelectTrigger id="payout-method">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {METHODS.map((m) => (
                                        <SelectItem key={m} value={m}>
                                            {t(`ambassador.method.${m}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="payout-account">
                                {t('ambassador.payout_account')}
                            </Label>
                            <Input
                                id="payout-account"
                                value={account}
                                onChange={(e) => setAccount(e.target.value)}
                                placeholder={t(
                                    'ambassador.payout_account_hint',
                                )}
                                required
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="payout-note">
                                {t('ambassador.payout_note')}
                            </Label>
                            <Input
                                id="payout-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                        <p className="text-center text-xs text-muted-foreground">
                            {t('ambassador.payout_amount_hint', {
                                amount: wallet.available.toLocaleString(),
                            })}
                        </p>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={submitting || account.trim().length < 5}
                        >
                            {t('ambassador.payout_submit')}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </MobileShell>
    );
}
