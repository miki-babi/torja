import { Head } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/trainer';

type Summary = {
    month_gross: number;
    month_commission: number;
    month_net: number;
    pending_payout: number;
    paid_clients: number;
    commission_percent: number;
};

type Sale = {
    id: number;
    client_name: string | null;
    plan_name: string | null;
    gross_amount: number;
    net_amount: number;
    settled: boolean;
    created_at: string;
};

type Payout = {
    id: number;
    amount: number;
    status: string;
    sent_at: string | null;
    created_at: string;
};

type Props = {
    summary: Summary;
    recentSales: Sale[];
    payouts: Payout[];
};

const etb = (amount: number) =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} ETB`;

const date = (value: string | null) =>
    value ? new Date(value).toLocaleDateString() : '—';

export default function TrainerEarnings({
    summary,
    recentSales,
    payouts,
}: Props) {
    return (
        <>
            <Head title="Earnings" />
            <div className="grid gap-6">
                <AdminPageHeader
                    title="Earnings"
                    description={`Plan sales minus the ${summary.commission_percent}% platform commission.`}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>This month</CardDescription>
                            <CardTitle className="text-xl">
                                {etb(summary.month_net)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            {etb(summary.month_gross)} gross ·{' '}
                            {etb(summary.month_commission)} commission
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Pending payout</CardDescription>
                            <CardTitle className="text-xl">
                                {etb(summary.pending_payout)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Sent to your payout account by the platform
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Paying clients</CardDescription>
                            <CardTitle className="text-xl">
                                {summary.paid_clients}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Clients on an active plan
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Commission</CardDescription>
                            <CardTitle className="text-xl">
                                {summary.commission_percent}%
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Platform fee per sale
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent sales
                        </CardTitle>
                        <CardDescription>
                            Latest coaching plan payments.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {recentSales.length === 0 && (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No sales yet.
                            </p>
                        )}
                        {recentSales.map((sale) => (
                            <div
                                key={sale.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {sale.client_name ?? 'Client'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {sale.plan_name ?? 'Plan'} ·{' '}
                                        {date(sale.created_at)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            sale.settled
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {sale.settled ? 'Paid out' : 'Pending'}
                                    </Badge>
                                    <span className="font-medium">
                                        +{etb(sale.net_amount)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Payouts</CardTitle>
                        <CardDescription>
                            Money sent to your payout account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {payouts.length === 0 && (
                            <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground">
                                <Wallet className="size-6" />
                                No payouts yet.
                            </div>
                        )}
                        {payouts.map((payout) => (
                            <div
                                key={payout.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <p className="text-xs text-muted-foreground">
                                    {payout.status === 'sent'
                                        ? `Sent ${date(payout.sent_at)}`
                                        : `Requested ${date(payout.created_at)}`}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            payout.status === 'sent'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {payout.status}
                                    </Badge>
                                    <span className="font-medium">
                                        {etb(payout.amount)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrainerEarnings.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Earnings', href: '/trainer/earnings' },
    ],
};
