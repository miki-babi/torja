import { Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    CircleAlert,
    UserPlus,
    Users,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaymentStatusBadge } from '@/components/admin/payment-status-badge';
import { StatCard } from '@/components/admin/stat-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { analytics, dashboard } from '@/routes/admin';
import {
    index as paymentsIndex,
    show as showPayment,
} from '@/routes/admin/payments';
import type {
    AdminDashboardAttention,
    AdminDashboardSeries,
    AdminPayment,
    AdminStats,
    AdminUser,
} from '@/types';

type Props = {
    stats: AdminStats;
    series: AdminDashboardSeries[];
    attention: AdminDashboardAttention;
    recentPayments: AdminPayment[];
    recentUsers: Pick<AdminUser, 'id' | 'name' | 'email' | 'created_at'>[];
};

function pctDelta(current: number, previous: number): string | undefined {
    if (previous <= 0) {
        return current > 0 ? '+100%' : undefined;
    }
    const change = Math.round(((current - previous) / previous) * 100);
    return `${change >= 0 ? '+' : ''}${change}%`;
}

const chartDay = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });

export default function AdminDashboard({
    stats,
    series,
    attention,
    recentPayments,
    recentUsers,
}: Props) {
    const needsAttention =
        attention.pending_payments > 0 || attention.failed_payments_7d > 0;

    return (
        <>
            <Head title="Admin — dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Dashboard"
                    description="Store stats, growth and recent activity"
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={analytics()} prefetch>
                                View analytics
                            </Link>
                        </Button>
                    }
                />

                {needsAttention && (
                    <Card className="border-amber-500/40 bg-amber-500/5">
                        <CardContent className="flex flex-wrap items-center gap-3 p-4">
                            <CircleAlert className="size-5 text-amber-600 dark:text-amber-400" />
                            <div className="text-sm">
                                <span className="font-medium">
                                    Needs attention:
                                </span>{' '}
                                {attention.pending_payments > 0 && (
                                    <span>
                                        {attention.pending_payments} pending
                                        payment
                                        {attention.pending_payments === 1
                                            ? ''
                                            : 's'}
                                    </span>
                                )}
                                {attention.pending_payments > 0 &&
                                    attention.failed_payments_7d > 0 &&
                                    ' · '}
                                {attention.failed_payments_7d > 0 && (
                                    <span>
                                        {attention.failed_payments_7d} failed in
                                        the last 7 days
                                    </span>
                                )}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="ml-auto"
                                asChild
                            >
                                <Link href={paymentsIndex()} prefetch>
                                    Review payments
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total users"
                        value={stats.total_users}
                        icon={Users}
                        hint={`${stats.paid_users.toLocaleString()} paid`}
                    />
                    <StatCard
                        label="Revenue (ETB)"
                        value={stats.revenue_etb}
                        icon={Banknote}
                        delta={pctDelta(
                            stats.revenue_7d,
                            stats.revenue_prev_7d,
                        )}
                        trend={
                            stats.revenue_7d >= stats.revenue_prev_7d
                                ? 'up'
                                : 'down'
                        }
                        hint="7d vs prior 7d"
                    />
                    <StatCard
                        label="New users (7d)"
                        value={stats.new_users_7d}
                        icon={UserPlus}
                        delta={pctDelta(
                            stats.new_users_7d,
                            stats.new_users_prev_7d,
                        )}
                        trend={
                            stats.new_users_7d >= stats.new_users_prev_7d
                                ? 'up'
                                : 'down'
                        }
                        hint="vs prior 7d"
                    />
                    <StatCard
                        label="Conversion"
                        value={`${stats.conversion_pct}%`}
                        icon={BadgeCheck}
                        hint="users who paid"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Growth — last 30 days
                        </CardTitle>
                        <CardDescription>
                            Daily signups and successful payment revenue
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="h-64 w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart
                                    data={series}
                                    margin={{
                                        top: 4,
                                        right: 4,
                                        bottom: 0,
                                        left: 4,
                                    }}
                                >
                                    <defs>
                                        <linearGradient
                                            id="revenueFill"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stopColor="var(--chart-2)"
                                                stopOpacity={0.35}
                                            />
                                            <stop
                                                offset="100%"
                                                stopColor="var(--chart-2)"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                        <linearGradient
                                            id="signupsFill"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stopColor="var(--chart-1)"
                                                stopOpacity={0.35}
                                            />
                                            <stop
                                                offset="100%"
                                                stopColor="var(--chart-1)"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid
                                        vertical={false}
                                        stroke="var(--border)"
                                        strokeDasharray="4 4"
                                    />
                                    <XAxis
                                        dataKey="date"
                                        tickFormatter={chartDay}
                                        tickLine={false}
                                        axisLine={false}
                                        minTickGap={32}
                                        tick={{
                                            fontSize: 11,
                                            fill: 'var(--muted-foreground)',
                                        }}
                                    />
                                    <YAxis
                                        yAxisId="signups"
                                        tickLine={false}
                                        axisLine={false}
                                        width={32}
                                        tick={{
                                            fontSize: 11,
                                            fill: 'var(--muted-foreground)',
                                        }}
                                        allowDecimals={false}
                                    />
                                    <YAxis
                                        yAxisId="revenue"
                                        orientation="right"
                                        tickLine={false}
                                        axisLine={false}
                                        width={48}
                                        tick={{
                                            fontSize: 11,
                                            fill: 'var(--muted-foreground)',
                                        }}
                                        tickFormatter={(v: number) =>
                                            v >= 1000
                                                ? `${Math.round(v / 1000)}k`
                                                : `${v}`
                                        }
                                    />
                                    <Tooltip
                                        content={({
                                            active,
                                            payload,
                                            label,
                                        }) =>
                                            active && payload?.length ? (
                                                <div className="rounded-md border bg-popover px-3 py-2 text-xs shadow-md">
                                                    <p className="mb-1 font-medium">
                                                        {chartDay(
                                                            String(label),
                                                        )}
                                                    </p>
                                                    {payload.map((p) => (
                                                        <p
                                                            key={String(
                                                                p.dataKey,
                                                            )}
                                                            className="text-muted-foreground"
                                                        >
                                                            {p.name}:{' '}
                                                            <span className="font-medium text-foreground">
                                                                {Number(
                                                                    p.value,
                                                                ).toLocaleString()}
                                                                {p.dataKey ===
                                                                    'revenue' &&
                                                                    ' ETB'}
                                                            </span>
                                                        </p>
                                                    ))}
                                                </div>
                                            ) : null
                                        }
                                    />
                                    <Area
                                        yAxisId="revenue"
                                        type="monotone"
                                        dataKey="revenue"
                                        name="Revenue"
                                        stroke="var(--chart-2)"
                                        strokeWidth={2}
                                        fill="url(#revenueFill)"
                                    />
                                    <Area
                                        yAxisId="signups"
                                        type="monotone"
                                        dataKey="signups"
                                        name="Signups"
                                        stroke="var(--chart-1)"
                                        strokeWidth={2}
                                        fill="url(#signupsFill)"
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-base">
                                    Recent payments
                                </CardTitle>
                                <CardDescription>
                                    The five most recent payment attempts
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={paymentsIndex()} prefetch>
                                    View all
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentPayments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No payments yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>User</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentPayments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell>
                                                    <div className="max-w-48 truncate font-medium">
                                                        {payment.user?.email ??
                                                            payment.email}
                                                    </div>
                                                    <div className="max-w-48 truncate font-mono text-xs text-muted-foreground">
                                                        {payment.tx_ref}
                                                    </div>
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
                                                            href={showPayment(
                                                                payment.id,
                                                            )}
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Latest signups
                            </CardTitle>
                            <CardDescription>
                                The five most recent registrations
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentUsers.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No users yet.
                                </p>
                            )}
                            {recentUsers.map((user) => (
                                <div
                                    key={user.id}
                                    className="flex items-center gap-3"
                                >
                                    <Avatar className="size-8">
                                        <AvatarFallback className="text-xs">
                                            {(user.name ?? '?')
                                                .slice(0, 2)
                                                .toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {user.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {user.email ?? '—'}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Admin',
            href: dashboard(),
        },
    ],
};
