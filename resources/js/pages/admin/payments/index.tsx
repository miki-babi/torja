import { Head, router } from '@inertiajs/react';
import { CheckCircle2, CreditCard, Search, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/admin/payments';
import type { Paginated } from '@/types';

type PaymentRow = {
    id: number;
    reference: string;
    user: string | null;
    item: string | null;
    purpose: string | null;
    amount: number;
    status: string;
    paid_at: string | null;
    created_at: string;
};

type Props = {
    payments: Paginated<PaymentRow>;
    filters: { status: string; search: string };
    totals: { success: number; pending: number; failed: number };
};

export default function AdminPaymentsIndex({
    payments,
    filters,
    totals,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    function apply(next: { search?: string; status?: string }) {
        router.get(
            index().url,
            { search: next.search ?? search, status: next.status ?? status },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Payments"
                    description="Every checkout attempt across the platform."
                    actions={
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                apply({ search });
                            }}
                            className="relative"
                        >
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search ref or payer…"
                                className="w-64 pl-8"
                            />
                        </form>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Successful volume"
                        value={`${totals.success.toLocaleString()} ETB`}
                        icon={CheckCircle2}
                    />
                    <StatCard
                        label="Pending"
                        value={totals.pending}
                        icon={CreditCard}
                    />
                    <StatCard
                        label="Failed"
                        value={totals.failed}
                        icon={XCircle}
                    />
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">
                            Transactions
                        </CardTitle>
                        <div className="flex gap-1">
                            {['', 'success', 'pending', 'failed'].map((s) => (
                                <button
                                    key={s || 'all'}
                                    type="button"
                                    onClick={() => {
                                        setStatus(s);
                                        apply({ status: s });
                                    }}
                                    className={`rounded-full px-3 py-1 text-xs font-medium ${
                                        status === s
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {s === '' ? 'All' : s}
                                </button>
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Payer</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Date</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell className="font-mono text-xs">
                                            {payment.reference}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {payment.user ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {payment.item}
                                            {payment.purpose ===
                                                'plan_purchase' && (
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    (plan)
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {payment.amount.toLocaleString()}{' '}
                                            ETB
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    payment.status ===
                                                    'success'
                                                        ? 'default'
                                                        : payment.status ===
                                                            'failed'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                            >
                                                {payment.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {payment.paid_at ??
                                                payment.created_at}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <AdminPagination paginated={payments} />
            </div>
        </>
    );
}
