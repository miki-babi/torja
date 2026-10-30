import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Send, Trash2, Undo2 } from 'lucide-react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { TelegramPreview } from '@/components/admin/broadcasts/telegram-preview';
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
import {
    cancel,
    edit,
    pause,
    resume,
    sendNow,
    show,
    test,
} from '@/routes/admin/broadcasts';
import { recall, resendFailed } from '@/routes/admin/broadcasts/runs';
import type {
    AdminBroadcast,
    BroadcastDelivery,
    Paginated,
} from '@/types';

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'outline',
    sent: 'secondary',
    failed: 'destructive',
    blocked: 'destructive',
    skipped: 'outline',
};

function userLabel(user: BroadcastDelivery['user']): string {
    if (user === null) {
        return 'Unknown';
    }
    return (
        user.name ||
        (user.telegram_username !== null ? `@${user.telegram_username}` : '') ||
        user.phone ||
        `#${user.id}`
    );
}

export default function BroadcastShow({
    broadcast,
    deliveries,
    filters,
}: {
    broadcast: AdminBroadcast;
    deliveries: Paginated<BroadcastDelivery>;
    filters: { status: string | null; run: number | null };
}) {
    const { confirm, confirmDialog } = useConfirm();
    const latestRun = broadcast.runs?.[0] ?? null;

    function filter(status: string | null, run: number | null) {
        router.get(
            show.url(broadcast.id),
            { status: status ?? undefined, run: run ?? undefined },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title={`Admin — ${broadcast.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={broadcast.name}
                    description={`${broadcast.audience} · ${broadcast.recurrence !== 'none' ? `repeats ${broadcast.recurrence}` : 'one-off'}`}
                    actions={
                        <>
                            {broadcast.editable && (
                                <Button variant="outline" asChild>
                                    <Link href={edit.url(broadcast.id)}>
                                        <Pencil className="size-4" /> Edit
                                    </Link>
                                </Button>
                            )}
                            {broadcast.editable && (
                                <Button
                                    variant="secondary"
                                    onClick={() =>
                                        router.post(sendNow.url(broadcast.id))
                                    }
                                >
                                    <Send className="size-4" /> Send now
                                </Button>
                            )}
                            {broadcast.status === 'scheduled' && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                pause.url(broadcast.id),
                                            )
                                        }
                                    >
                                        Pause
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            confirm({
                                                title: 'Cancel broadcast?',
                                                description:
                                                    'Scheduled sends stop; already-delivered messages stay.',
                                                onConfirm: () =>
                                                    router.post(
                                                        cancel.url(
                                                            broadcast.id,
                                                        ),
                                                    ),
                                            })
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </>
                            )}
                            {broadcast.status === 'paused' && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(resume.url(broadcast.id))
                                    }
                                >
                                    Resume
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.post(test.url(broadcast.id))
                                }
                            >
                                Send test to me
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Runs
                                </CardTitle>
                                <CardDescription>
                                    Status:{' '}
                                    <Badge
                                        variant={
                                            STATUS_VARIANT[
                                                broadcast.status
                                            ] ?? 'outline'
                                        }
                                    >
                                        {broadcast.status}
                                    </Badge>{' '}
                                    {broadcast.next_run_at !== null &&
                                        `· next ${broadcast.next_run_at}`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {(broadcast.runs ?? []).length === 0 ? (
                                    <EmptyState
                                        title="Never sent"
                                        description="This broadcast hasn't been dispatched yet."
                                    />
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Run</TableHead>
                                                <TableHead>Trigger</TableHead>
                                                <TableHead>Recipients</TableHead>
                                                <TableHead>Sent</TableHead>
                                                <TableHead>Failed</TableHead>
                                                <TableHead>Blocked</TableHead>
                                                <TableHead>Recalled</TableHead>
                                                <TableHead>Finished</TableHead>
                                                <TableHead />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {(broadcast.runs ?? []).map(
                                                (run) => (
                                                    <TableRow key={run.id}>
                                                        <TableCell className="tabular-nums">
                                                            #{run.id}
                                                        </TableCell>
                                                        <TableCell>
                                                            {run.trigger}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.recipients_count}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.sent_count}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.failed_count}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.blocked_count}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.deleted_count}
                                                        </TableCell>
                                                        <TableCell className="text-xs tabular-nums">
                                                            {run.finished_at ??
                                                                (run.started_at !==
                                                                null
                                                                    ? 'running…'
                                                                    : '—')}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                {run.failed_count >
                                                                    0 && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        title="Retry failed deliveries"
                                                                        onClick={() =>
                                                                            router.post(
                                                                                resendFailed.url(
                                                                                    {
                                                                                        broadcast:
                                                                                            broadcast.id,
                                                                                        run: run.id,
                                                                                    },
                                                                                ),
                                                                            )
                                                                        }
                                                                    >
                                                                        <Undo2 className="size-3" />
                                                                    </Button>
                                                                )}
                                                                {run.sent_count >
                                                                    run.deleted_count && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        title="Delete messages from chats"
                                                                        onClick={() =>
                                                                            confirm({
                                                                                title: 'Recall broadcast?',
                                                                                description:
                                                                                    'Messages will be deleted from recipients\' chats. This cannot be undone.',
                                                                                destructive: true,
                                                                                onConfirm: () =>
                                                                                    router.post(
                                                                                        recall.url(
                                                                                            {
                                                                                                broadcast:
                                                                                                    broadcast.id,
                                                                                                run: run.id,
                                                                                            },
                                                                                        ),
                                                                                    ),
                                                                            })
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-3" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Deliveries
                                </CardTitle>
                                <CardDescription className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={filters.run?.toString() ?? ''}
                                        onValueChange={(v) =>
                                            filter(
                                                filters.status,
                                                v === 'all' ? null : Number(v),
                                            )
                                        }
                                    >
                                        <SelectTrigger className="h-8 w-40">
                                            <SelectValue placeholder="All runs" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All runs
                                            </SelectItem>
                                            {(broadcast.runs ?? []).map(
                                                (run) => (
                                                    <SelectItem
                                                        key={run.id}
                                                        value={run.id.toString()}
                                                    >
                                                        Run #{run.id}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={filters.status ?? ''}
                                        onValueChange={(v) =>
                                            filter(
                                                v === 'all' ? null : v,
                                                filters.run,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="h-8 w-36">
                                            <SelectValue placeholder="All statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All statuses
                                            </SelectItem>
                                            {[
                                                'pending',
                                                'sent',
                                                'failed',
                                                'blocked',
                                                'skipped',
                                            ].map((status) => (
                                                <SelectItem
                                                    key={status}
                                                    value={status}
                                                >
                                                    {status}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {deliveries.data.length === 0 ? (
                                    <EmptyState title="No deliveries" />
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>User</TableHead>
                                                <TableHead>Run</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Sent at</TableHead>
                                                <TableHead>Error</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {deliveries.data.map(
                                                (delivery) => (
                                                    <TableRow key={delivery.id}>
                                                        <TableCell>
                                                            <div className="text-sm font-medium">
                                                                {userLabel(
                                                                    delivery.user,
                                                                )}
                                                            </div>
                                                            {delivery.user
                                                                ?.telegram_username && (
                                                                <div className="text-xs text-muted-foreground">
                                                                    @
                                                                    {
                                                                        delivery
                                                                            .user
                                                                            .telegram_username
                                                                    }
                                                                </div>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            #{delivery.run_id}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant={
                                                                    STATUS_VARIANT[
                                                                        delivery
                                                                            .status
                                                                    ] ??
                                                                    'outline'
                                                                }
                                                            >
                                                                {delivery.status}
                                                                {delivery.deleted_at !==
                                                                    null &&
                                                                    ' · recalled'}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-xs tabular-nums">
                                                            {delivery.sent_at ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell className="max-w-64 truncate text-xs text-muted-foreground">
                                                            {delivery.error ??
                                                                '—'}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>

                        <AdminPagination page={deliveries} />
                    </div>

                    <div className="lg:sticky lg:top-4 lg:self-start">
                        <TelegramPreview
                            type={broadcast.type}
                            bodyHtml={broadcast.body_html ?? ''}
                            media={broadcast.media ?? []}
                            buttons={broadcast.buttons ?? []}
                            poll={broadcast.poll ?? null}
                        />
                    </div>
                </div>
            </div>
            {confirmDialog}
        </>
    );
}
