import { Head, Link, router } from '@inertiajs/react';
import { Copy, MessageCircle, Search, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/trainer';
import { index, show, destroy, message } from '@/routes/trainer/clients';
import type { Paginated } from '@/types';

type Client = {
    id: number;
    name: string;
    username: string | null;
    avatar: string | null;
    joined: string | null;
    sessions_count: number;
    on_telegram: boolean;
};

type Props = {
    clients: Paginated<Client>;
    filters: { search: string };
    invite_url: string;
};

export default function TrainerClientsIndex({
    clients,
    filters,
    invite_url,
}: Props) {
    const [, copy] = useClipboard();
    const { confirm, confirmDialog } = useConfirm();
    const [search, setSearch] = useState(filters.search);

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            index().url,
            { search },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Clients" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Clients"
                    description="Members who joined through your bot or invite link."
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={async () => {
                                if (await copy(invite_url)) {
                                    toast.success('Invite link copied');
                                }
                            }}
                        >
                            <Copy className="size-4" />
                            Copy invite link
                        </Button>
                    }
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle className="text-base">Roster</CardTitle>
                            <CardDescription>
                                {clients.total} client
                                {clients.total === 1 ? '' : 's'}
                            </CardDescription>
                        </div>
                        <form onSubmit={submitSearch} className="relative">
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search clients…"
                                className="w-56 pl-8"
                            />
                        </form>
                    </CardHeader>
                    <CardContent>
                        {clients.data.length === 0 ? (
                            <EmptyState
                                icon={UserRound}
                                title="No clients yet"
                                description="Share your invite link or your bot — clients appear here when they join."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Client</TableHead>
                                        <TableHead className="text-right">
                                            Workouts
                                        </TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead>Telegram</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {clients.data.map((client) => (
                                        <TableRow key={client.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(client.id).url}
                                                    className="flex items-center gap-3 hover:underline"
                                                >
                                                    <Avatar className="size-8">
                                                        <AvatarImage
                                                            src={
                                                                client.avatar ??
                                                                undefined
                                                            }
                                                        />
                                                        <AvatarFallback>
                                                            {client.name.slice(
                                                                0,
                                                                1,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {client.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {client.username ??
                                                                '—'}
                                                        </p>
                                                    </div>
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {client.sessions_count}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {client.joined ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {client.on_telegram
                                                    ? 'Linked'
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Message"
                                                        onClick={() =>
                                                            router.post(
                                                                message(
                                                                    client.id,
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        <MessageCircle className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-destructive"
                                                        title="Remove client"
                                                        onClick={() =>
                                                            confirm({
                                                                title: `Remove ${client.name}?`,
                                                                description:
                                                                    'They keep their plan copies but leave your roster.',
                                                                confirmLabel:
                                                                    'Remove',
                                                                destructive: true,
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        destroy(
                                                                            client.id,
                                                                        ).url,
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <div className="mt-4">
                            <AdminPagination page={clients} />
                        </div>
                    </CardContent>
                </Card>
            </div>
            {confirmDialog}
        </>
    );
}

TrainerClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Clients', href: '/trainer/clients' },
    ],
};
