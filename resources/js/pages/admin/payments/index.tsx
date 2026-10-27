import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { PaymentStatusBadge } from '@/components/admin/payment-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { index, show } from '@/routes/admin/payments';
import type { AdminPayment, Paginated } from '@/types';

type Props = {
    payments: Paginated<AdminPayment>;
    filters: { search: string; status: string; provider: string };
};

const STATUSES = [
    'pending',
    'success',
    'failed',
    'expired',
    'cancelled',
    'review_required',
    'refunded',
];

const PROVIDERS = ['chapa', 'verify_checkout', 'jami'];

const PROVIDER_LABELS: Record<string, string> = {
    chapa: 'Chapa',
    verify_checkout: 'Verify Checkout',
    jami: 'Jami',
};

export default function AdminPaymentsIndex({ payments, filters }: Props) {
    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            index().url,
            {
                search: data.get('search'),
                status: filters.status || undefined,
                provider: filters.provider || undefined,
            },
            { preserveState: true },
        );
    }

    function filterStatus(status: string) {
        router.get(
            index().url,
            {
                search: filters.search || undefined,
                status: status || undefined,
                provider: filters.provider || undefined,
            },
            { preserveState: true },
        );
    }

    function filterProvider(provider: string) {
        router.get(
            index().url,
            {
                search: filters.search || undefined,
                status: filters.status || undefined,
                provider: provider || undefined,
            },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title="Admin — payments" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Payments"
                    description="All payment attempts"
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            All payments
                        </CardTitle>
                        <CardDescription>
                            {payments.total.toLocaleString()} attempts — search
                            by reference or user email
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <form
                                onSubmit={submitSearch}
                                className="flex gap-2"
                            >
                                <div className="relative max-w-xs flex-1">
                                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="search"
                                        defaultValue={filters.search}
                                        placeholder="Search reference or email…"
                                        className="pl-8"
                                    />
                                </div>
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                            <Select
                                value={
                                    filters.status === ''
                                        ? 'all'
                                        : filters.status
                                }
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
                                    {STATUSES.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={
                                    filters.provider === ''
                                        ? 'all'
                                        : filters.provider
                                }
                                onValueChange={(value) =>
                                    filterProvider(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="Provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All providers
                                    </SelectItem>
                                    {PROVIDERS.map((provider) => (
                                        <SelectItem
                                            key={provider}
                                            value={provider}
                                        >
                                            {PROVIDER_LABELS[provider] ??
                                                provider}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {payments.data.length === 0 ? (
                            <EmptyState
                                title="No payments found"
                                description="Try a different search or status filter."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Reference</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="w-16" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.data.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="max-w-48 truncate font-medium">
                                                {payment.user?.email ??
                                                    payment.email}
                                            </TableCell>
                                            <TableCell className="max-w-40 truncate font-mono text-xs text-muted-foreground">
                                                {payment.deposit_id ??
                                                    payment.tx_ref}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {PROVIDER_LABELS[
                                                    payment.provider
                                                ] ?? payment.provider}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {payment.amount}{' '}
                                                {payment.currency}
                                            </TableCell>
                                            <TableCell>
                                                <PaymentStatusBadge
                                                    status={payment.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {new Date(
                                                    payment.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={show(payment.id)}
                                                        prefetch
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <AdminPagination page={payments} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminPaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Payments', href: index() },
    ],
};
