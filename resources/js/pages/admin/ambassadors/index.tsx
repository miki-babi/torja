import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Banknote,
    CheckCircle2,
    Hourglass,
    Settings,
    XCircle,
} from 'lucide-react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/ambassadors';
import {
    approve as approveCommission,
    voidMethod as voidCommission,
} from '@/routes/admin/ambassadors/commissions';
import {
    paid as markPayoutPaid,
    reject as rejectPayout,
} from '@/routes/admin/ambassadors/payouts';
import { edit as editSettings } from '@/routes/admin/ambassadors/settings';
import type { Paginated } from '@/types';

type Person = {
    id: number;
    name: string;
    phone?: string | null;
    email?: string | null;
} | null;

type Commission = {
    id: number;
    amount: string;
    status: string;
    void_reason: string | null;
    created_at: string;
    affiliate: Person;
    referred_user: Person;
    payment: { id: number; amount: string } | null;
};

type Payout = {
    id: number;
    amount: string;
    method: string;
    account: string;
    note: string | null;
    status: string;
    created_at: string;
    user: Person;
};

type Props = {
    stats: {
        pending: number;
        approved_unpaid: number;
        requested_payouts: number;
        paid_total: number;
    };
    pendingCommissions: Commission[];
    commissions: Paginated<Commission>;
    payoutRequests: Payout[];
    recentPayouts: Payout[];
    filters: { status: string };
};

const COMMISSION_STATUSES = ['pending', 'approved', 'paid', 'void'];

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    requested: 'secondary',
    approved: 'default',
    paid: 'default',
    void: 'destructive',
    rejected: 'destructive',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant={STATUS_VARIANT[status] ?? 'outline'}
            className="capitalize"
        >
            {status}
        </Badge>
    );
}

function personLabel(person: Person): string {
    if (person === null) {
        return '—';
    }

    return person.name || person.email || person.phone || `#${person.id}`;
}

export default function AdminAmbassadorsIndex({
    stats,
    pendingCommissions,
    commissions,
    payoutRequests,
    recentPayouts,
    filters,
}: Props) {
    const { confirm, confirmDialog } = useConfirm();

    function filterStatus(status: string) {
        router.get(
            index().url,
            { status: status || undefined },
            { preserveState: true },
        );
    }

    function approve(id: number) {
        router.post(approveCommission(id).url);
    }

    function rejectCommission(id: number) {
        confirm({
            title: 'Void this commission?',
            description:
                'The amount is removed from the ambassador’s payable balance. This cannot be undone.',
            confirmLabel: 'Void commission',
            destructive: true,
            onConfirm: () => router.post(voidCommission(id).url),
        });
    }

    function markPaid(id: number) {
        confirm({
            title: 'Mark payout as paid?',
            description:
                'Confirm only after the money has been sent to the ambassador. All covered commissions become paid.',
            confirmLabel: 'Mark paid',
            onConfirm: () => router.post(markPayoutPaid(id).url),
        });
    }

    function rejectPayoutRequest(id: number) {
        confirm({
            title: 'Reject this payout request?',
            description:
                'The commissions return to the ambassador’s approved balance so they can request again.',
            confirmLabel: 'Reject payout',
            destructive: true,
            onConfirm: () => router.post(rejectPayout(id).url),
        });
    }

    return (
        <>
            <Head title="Ambassadors" />
            <div className="flex flex-1 flex-col gap-6 p-4 lg:p-6">
                <AdminPageHeader
                    title="Ambassadors"
                    description="Referral commissions and payout requests."
                    actions={
                        <Button asChild variant="outline" size="sm">
                            <Link href={editSettings()} prefetch>
                                <Settings />
                                Settings
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Pending approval"
                        value={stats.pending}
                        icon={Hourglass}
                    />
                    <StatCard
                        label="Approved unpaid"
                        value={`${stats.approved_unpaid.toLocaleString()} ETB`}
                        icon={BadgeDollarSign}
                    />
                    <StatCard
                        label="Payout requests"
                        value={stats.requested_payouts}
                        icon={Banknote}
                    />
                    <StatCard
                        label="Total paid"
                        value={`${stats.paid_total.toLocaleString()} ETB`}
                        icon={CheckCircle2}
                    />
                </div>

                {/* Pending approval queue */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pending approvals</CardTitle>
                        <CardDescription>
                            Approve commissions to make them payable, or void
                            suspicious ones.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {pendingCommissions.length === 0 ? (
                            <EmptyState
                                title="Nothing to review"
                                description="New commissions appear here when a referred member pays for Pro."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ambassador</TableHead>
                                        <TableHead>Referred</TableHead>
                                        <TableHead>Payment</TableHead>
                                        <TableHead>Commission</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pendingCommissions.map((commission) => (
                                        <TableRow key={commission.id}>
                                            <TableCell>
                                                {personLabel(
                                                    commission.affiliate,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {personLabel(
                                                    commission.referred_user,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {commission.payment !== null
                                                    ? `${Number(commission.payment.amount).toLocaleString()} ETB`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {Number(
                                                    commission.amount,
                                                ).toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell>
                                                {new Date(
                                                    commission.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            approve(
                                                                commission.id,
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2 />
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            rejectCommission(
                                                                commission.id,
                                                            )
                                                        }
                                                    >
                                                        <XCircle />
                                                        Void
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Payout requests */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payout requests</CardTitle>
                        <CardDescription>
                            Send the money manually to the ambassador’s account,
                            then mark the payout paid.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payoutRequests.length === 0 ? (
                            <EmptyState
                                title="No open requests"
                                description="Payout requests from ambassadors appear here."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ambassador</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Requested</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payoutRequests.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell>
                                                {personLabel(payout.user)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {payout.user?.phone ??
                                                    payout.user?.email ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {payout.method}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {payout.account}
                                                {payout.note !== null && (
                                                    <span className="block text-muted-foreground">
                                                        {payout.note}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {Number(
                                                    payout.amount,
                                                ).toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell>
                                                {new Date(
                                                    payout.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            markPaid(payout.id)
                                                        }
                                                    >
                                                        <CheckCircle2 />
                                                        Mark paid
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            rejectPayoutRequest(
                                                                payout.id,
                                                            )
                                                        }
                                                    >
                                                        <XCircle />
                                                        Reject
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* All commissions */}
                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle>Commissions</CardTitle>
                            <CardDescription>
                                Every referral commission by status.
                            </CardDescription>
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                filterStatus(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {COMMISSION_STATUSES.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        <span className="capitalize">
                                            {status}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardHeader>
                    <CardContent>
                        {commissions.data.length === 0 ? (
                            <EmptyState
                                title="No commissions"
                                description="Nothing here yet."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ambassador</TableHead>
                                        <TableHead>Referred</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {commissions.data.map((commission) => (
                                        <TableRow key={commission.id}>
                                            <TableCell>
                                                {personLabel(
                                                    commission.affiliate,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {personLabel(
                                                    commission.referred_user,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {Number(
                                                    commission.amount,
                                                ).toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={commission.status}
                                                />
                                                {commission.void_reason !==
                                                    null && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {commission.void_reason}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(
                                                    commission.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <AdminPagination page={commissions} />
                    </CardContent>
                </Card>

                {/* Recent payouts */}
                {recentPayouts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent payouts</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ambassador</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentPayouts.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell>
                                                {personLabel(payout.user)}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {payout.method}
                                            </TableCell>
                                            <TableCell>
                                                {Number(
                                                    payout.amount,
                                                ).toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={payout.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {new Date(
                                                    payout.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
            {confirmDialog}
        </>
    );
}

AdminAmbassadorsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Ambassadors', href: index() },
    ],
};
