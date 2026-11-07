import { Form, Head } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/trainer';
import { store } from '@/routes/trainer/broadcasts';
import type { Paginated } from '@/types';

type Broadcast = {
    id: number;
    body: string;
    sent_count: number;
    failed_count: number;
    created_at: string;
};

type Props = {
    broadcasts: Paginated<Broadcast>;
    clients_count: number;
    bot_connected: boolean;
};

export default function TrainerBroadcastsIndex({
    broadcasts,
    clients_count,
    bot_connected,
}: Props) {
    return (
        <>
            <Head title="Broadcasts" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Broadcasts"
                    description="Send a Telegram message to all your clients at once."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            New broadcast
                        </CardTitle>
                        <CardDescription>
                            Goes out via your bot to {clients_count} client
                            {clients_count === 1 ? '' : 's'} with Telegram
                            linked.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {bot_connected ? (
                            <Form
                                {...store.form()}
                                resetOnSuccess
                                className="grid gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <textarea
                                            name="body"
                                            rows={4}
                                            maxLength={4096}
                                            placeholder="New challenge starting Monday — check your plan! 💪"
                                            required
                                            className="border-input bg-transparent placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                        />
                                        <InputError message={errors.body} />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-fit"
                                        >
                                            {processing && <Spinner />}
                                            <Megaphone className="size-4" />
                                            Send to all clients
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Connect your bot on the Bot setup page before
                                broadcasting.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {broadcasts.data.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="No broadcasts yet"
                                description="Your sent messages will appear here."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Message</TableHead>
                                        <TableHead className="text-right">
                                            Sent
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Failed
                                        </TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {broadcasts.data.map((broadcast) => (
                                        <TableRow key={broadcast.id}>
                                            <TableCell className="max-w-md truncate">
                                                {broadcast.body}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {broadcast.sent_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {broadcast.failed_count}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {broadcast.created_at}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <div className="mt-4">
                            <AdminPagination page={broadcasts} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrainerBroadcastsIndex.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Broadcasts', href: '/trainer/broadcasts' },
    ],
};
