import { Head, Link } from '@inertiajs/react';
import { Activity, CalendarDays, MousePointerClick, Users } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AdminPageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes/admin';
import { segment } from '@/routes/admin/analytics';

type Props = {
    engagement: {
        dau: number;
        wau: number;
        mau: number;
        total_events: number;
        events_30d: number;
    };
    featureSplit: Record<string, number>;
    hourGrid: Record<string, number>;
    topEvents: Record<string, number>;
    wizardFunnel: Record<string, number>;
    social: {
        playlists_created: number;
        playlists_shared: number;
        shared_views: number;
        shared_saves: number;
    };
    segments: { key: string; label: string; count: number }[];
    traffic: {
        channel: string;
        visitors: number;
        registered: number;
        wizard_done: number;
        paid: number;
        conv_pct: number;
        goals: Record<string, number>;
    }[];
};

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { value?: number; name?: string }[];
    label?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-xs shadow-md">
            <p className="mb-1 font-medium">{label}</p>
            {payload.map((p, i) => (
                <p key={i} className="text-muted-foreground">
                    {p.name}:{' '}
                    <span className="font-medium text-foreground">
                        {Number(p.value).toLocaleString()}
                    </span>
                </p>
            ))}
        </div>
    );
}

export default function AdminAnalytics({
    engagement,
    featureSplit,
    hourGrid,
    topEvents,
    wizardFunnel,
    social,
    segments,
    traffic,
}: Props) {
    const maxHour = Math.max(1, ...Object.values(hourGrid));

    const featureData = Object.entries(featureSplit).map(([name, views]) => ({
        name,
        views,
    }));
    const topEventData = Object.entries(topEvents).map(([name, hits]) => ({
        name,
        hits,
    }));
    const funnelData = Object.entries(wizardFunnel)
        .map(([name, hits]) => ({ name, hits }))
        .sort((a, b) => b.hits - a.hits);
    const maxFunnel = Math.max(1, ...funnelData.map((d) => d.hits));

    return (
        <>
            <Head title="Admin — analytics" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Analytics"
                    description="Engagement, feature usage, funnels and audiences"
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        label="DAU"
                        value={engagement.dau}
                        icon={Activity}
                    />
                    <StatCard label="WAU" value={engagement.wau} icon={Users} />
                    <StatCard
                        label="MAU"
                        value={engagement.mau}
                        icon={CalendarDays}
                    />
                    <StatCard
                        label="Events (30d)"
                        value={engagement.events_30d}
                        icon={MousePointerClick}
                    />
                    <StatCard
                        label="Events (all time)"
                        value={engagement.total_events}
                        icon={MousePointerClick}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Feature usage (30d)
                            </CardTitle>
                            <CardDescription>
                                Page views by section
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                className="w-full"
                                style={{
                                    height: Math.max(
                                        160,
                                        featureData.length * 34,
                                    ),
                                }}
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={featureData}
                                        layout="vertical"
                                        margin={{
                                            top: 0,
                                            right: 12,
                                            bottom: 0,
                                            left: 8,
                                        }}
                                    >
                                        <CartesianGrid
                                            horizontal={false}
                                            stroke="var(--border)"
                                            strokeDasharray="4 4"
                                        />
                                        <XAxis type="number" hide />
                                        <YAxis
                                            type="category"
                                            dataKey="name"
                                            width={90}
                                            tickLine={false}
                                            axisLine={false}
                                            tick={{
                                                fontSize: 12,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                        />
                                        <Tooltip content={<ChartTooltip />} />
                                        <Bar
                                            dataKey="views"
                                            name="Views"
                                            fill="var(--chart-2)"
                                            radius={[0, 4, 4, 0]}
                                            barSize={14}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Active hours (30d)
                            </CardTitle>
                            <CardDescription>
                                Event volume by weekday and hour
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5">
                                {DAYS.map((day, weekday) => (
                                    <div key={day} className="contents">
                                        <span className="w-8 text-[10px] text-muted-foreground">
                                            {day}
                                        </span>
                                        <div className="grid grid-cols-24 gap-px">
                                            {Array.from(
                                                { length: 24 },
                                                (_, h) => {
                                                    const hits =
                                                        hourGrid[
                                                            `${weekday}:${h}`
                                                        ] ?? 0;
                                                    const intensity =
                                                        hits / maxHour;
                                                    return (
                                                        <div
                                                            key={h}
                                                            title={`${day} ${h}:00 — ${hits} events`}
                                                            className="h-4 rounded-[2px]"
                                                            style={{
                                                                backgroundColor:
                                                                    hits === 0
                                                                        ? 'var(--muted)'
                                                                        : `oklch(${1 - intensity * 0.5} 0.15 140)`,
                                                            }}
                                                        />
                                                    );
                                                },
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-3 flex items-center justify-end gap-1.5 text-[10px] text-muted-foreground">
                                <span>Less</span>
                                {[0, 0.25, 0.5, 0.75, 1].map((i) => (
                                    <div
                                        key={i}
                                        className="size-3 rounded-[2px]"
                                        style={{
                                            backgroundColor:
                                                i === 0
                                                    ? 'var(--muted)'
                                                    : `oklch(${1 - i * 0.5} 0.15 140)`,
                                        }}
                                    />
                                ))}
                                <span>More</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Wizard funnel (all time)
                            </CardTitle>
                            <CardDescription>
                                Step completions by section
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {funnelData.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No wizard events yet.
                                </p>
                            )}
                            {funnelData.map(({ name, hits }) => (
                                <div key={name} className="grid gap-1">
                                    <div className="flex justify-between text-xs">
                                        <span>{name}</span>
                                        <span className="text-muted-foreground">
                                            {hits}
                                        </span>
                                    </div>
                                    <div className="h-2 rounded bg-muted">
                                        <div
                                            className="h-2 rounded bg-chart-2"
                                            style={{
                                                width: `${(hits / maxFunnel) * 100}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Playlists & sharing (30d)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {[
                                ['Playlists created', social.playlists_created],
                                [
                                    'Share links enabled',
                                    social.playlists_shared,
                                ],
                                ['Shared link views', social.shared_views],
                                ['Saved to account', social.shared_saves],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex justify-between"
                                >
                                    <span>{label}</span>
                                    <span className="font-medium tabular-nums">
                                        {value}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Top events (30d)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                className="w-full"
                                style={{
                                    height: Math.max(
                                        160,
                                        topEventData.length * 22,
                                    ),
                                }}
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={topEventData}
                                        layout="vertical"
                                        margin={{
                                            top: 0,
                                            right: 12,
                                            bottom: 0,
                                            left: 8,
                                        }}
                                    >
                                        <XAxis type="number" hide />
                                        <YAxis
                                            type="category"
                                            dataKey="name"
                                            width={140}
                                            tickLine={false}
                                            axisLine={false}
                                            tick={{
                                                fontSize: 10,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                        />
                                        <Tooltip content={<ChartTooltip />} />
                                        <Bar
                                            dataKey="hits"
                                            name="Hits"
                                            fill="var(--chart-1)"
                                            radius={[0, 4, 4, 0]}
                                            barSize={10}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Traffic → paying users (90d)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Channel</TableHead>
                                    <TableHead className="text-right">
                                        Visitors
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Signed up
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Wizard done
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Paid
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Conv %
                                    </TableHead>
                                    <TableHead>Top goals</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {traffic.map((row) => (
                                    <TableRow key={row.channel}>
                                        <TableCell className="font-medium">
                                            {row.channel}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.visitors}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.registered}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.wizard_done}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.paid}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.conv_pct}%
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {Object.entries(row.goals).map(
                                                    ([goal, count]) => (
                                                        <Badge
                                                            key={goal}
                                                            variant="secondary"
                                                        >
                                                            {goal} {count}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {traffic.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-4 text-muted-foreground"
                                        >
                                            No traffic yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Audience segments
                        </CardTitle>
                        <CardDescription>
                            Computed member cohorts — click to inspect members
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                        {segments.map((s) => (
                            <Link
                                key={s.key}
                                href={segment(s.key).url}
                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm transition-colors hover:bg-accent"
                            >
                                <span>{s.label}</span>
                                <Badge variant="secondary">{s.count}</Badge>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminAnalytics.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Analytics', href: '/admin/analytics' },
    ],
};
