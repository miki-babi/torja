import { Head, Link, router } from '@inertiajs/react';
import { Megaphone, MoreHorizontal, Plus } from 'lucide-react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    create,
    destroy,
    edit,
    sendNow,
    show,
} from '@/routes/admin/broadcasts';
import type { AdminBroadcast, Paginated } from '@/types';

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    draft: 'outline',
    scheduled: 'secondary',
    sending: 'default',
    sent: 'secondary',
    paused: 'outline',
    cancelled: 'destructive',
};

const TYPE_LABELS: Record<string, string> = {
    text: 'Text',
    photo: 'Photo',
    video: 'Video',
    document: 'Document',
    animation: 'GIF',
    media_group: 'Album',
    poll: 'Poll',
};

export default function BroadcastsIndex({
    broadcasts,
}: {
    broadcasts: Paginated<AdminBroadcast>;
}) {
    const { confirm, confirmDialog } = useConfirm();

    return (
        <>
            <Head title="Admin — Broadcasts" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Telegram broadcasts"
                    description="Rich messages pushed to members' linked Telegram chats."
                    actions={
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus className="size-4" /> New broadcast
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent className="p-0">
                        {broadcasts.data.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="No broadcasts yet"
                                description="Compose your first rich Telegram message — text, media, albums, polls, and every kind of inline button."
                                action={
                                    <Button asChild size="sm">
                                        <Link href={create().url}>
                                            <Plus className="size-4" /> New
                                            broadcast
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Audience</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Next run</TableHead>
                                        <TableHead>Last run</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {broadcasts.data.map((broadcast) => (
                                        <TableRow key={broadcast.id}>
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={
                                                        show.url(broadcast.id)
                                                    }
                                                    className="hover:underline"
                                                >
                                                    {broadcast.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {TYPE_LABELS[
                                                        broadcast.type
                                                    ] ?? broadcast.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="max-w-48 truncate text-sm text-muted-foreground">
                                                {broadcast.audience}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        STATUS_VARIANT[
                                                            broadcast.status
                                                        ] ?? 'outline'
                                                    }
                                                >
                                                    {broadcast.status}
                                                    {broadcast.recurrence !==
                                                    'none'
                                                        ? ` · ${broadcast.recurrence}`
                                                        : ''}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm tabular-nums">
                                                {broadcast.next_run_at ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm tabular-nums">
                                                {broadcast.last_run === null
                                                    ? '—'
                                                    : `${broadcast.last_run.sent_count}/${broadcast.last_run.recipients_count} sent`}
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={
                                                                    show.url(
                                                                        broadcast.id,
                                                                    )
                                                                }
                                                            >
                                                                View
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        {broadcast.editable && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={edit.url(
                                                                        broadcast.id,
                                                                    )}
                                                                >
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}
                                                        {broadcast.editable && (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        sendNow.url(
                                                                            broadcast.id,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                Send now
                                                            </DropdownMenuItem>
                                                        )}
                                                        {broadcast.status ===
                                                            'scheduled' && (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        cancel.url(
                                                                            broadcast.id,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                                schedule
                                                            </DropdownMenuItem>
                                                        )}
                                                        {broadcast.status ===
                                                            'draft' && (
                                                            <DropdownMenuItem
                                                                className="text-destructive"
                                                                onSelect={() =>
                                                                    confirm({
                                                                        title: 'Delete broadcast?',
                                                                        description: `“${broadcast.name}” and its delivery history will be removed.`,
                                                                        onConfirm: () =>
                                                                            router.delete(
                                                                                destroy.url(
                                                                                    broadcast.id,
                                                                                ),
                                                                            ),
                                                                    })
                                                                }
                                                            >
                                                                Delete
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <AdminPagination page={broadcasts} />
            </div>
            {confirmDialog}
        </>
    );
}
