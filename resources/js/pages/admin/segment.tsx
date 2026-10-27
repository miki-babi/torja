import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { analytics, dashboard } from '@/routes/admin';

type Member = {
    id: number;
    name: string | null;
    email: string | null;
    last_seen_at: string | null;
    sessions_30d: number;
    food_logs_30d: number;
    paid: boolean;
};

export default function AdminSegment({
    segment,
    members,
}: {
    segment: { key: string; label: string };
    members: Member[];
}) {
    return (
        <>
            <Head title={`Admin — ${segment.label}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={segment.label}
                    description={`${members.length} member${members.length === 1 ? '' : 's'} in this audience segment`}
                    actions={
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={analytics().url} prefetch>
                                    <ArrowLeft className="size-4" />
                                    Analytics
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/admin/analytics/segments/${segment.key}/export`}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </a>
                            </Button>
                        </>
                    }
                />

                <Card>
                    <CardContent className="p-0">
                        {members.length === 0 ? (
                            <EmptyState
                                title="No members in this segment"
                                description="Run traits:recompute after users accumulate events."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Member</TableHead>
                                        <TableHead className="text-right">
                                            Workouts (30d)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Meals (30d)
                                        </TableHead>
                                        <TableHead>Last seen</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((member) => (
                                        <TableRow key={member.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {member.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {member.email}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {member.sessions_30d}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {member.food_logs_30d}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {member.last_seen_at?.slice(
                                                    0,
                                                    10,
                                                ) ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {member.paid && (
                                                    <Badge>paid</Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminSegment.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Analytics', href: analytics() },
    ],
};
