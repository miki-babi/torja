import { Head, router } from '@inertiajs/react';
import {
    ArrowDownCircle,
    Bot,
    Dumbbell,
    Megaphone,
    Trash2,
    Unplug,
    Users,
    UtensilsCrossed,
    Wallet,
} from 'lucide-react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import {
    demote,
    destroy,
    index as trainersIndex,
    suspendBot,
} from '@/routes/admin/trainers';

type Client = {
    id: number;
    name: string;
    avatar: string | null;
    joined: string | null;
};

type Plan = {
    id: number;
    name: string;
    price_etb: number;
    is_free: boolean;
    shared: boolean;
};

type Broadcast = {
    id: number;
    body: string;
    sent_count: number;
    created_at: string;
};

type Props = {
    trainer: {
        id: number;
        slug: string;
        invite_url: string;
        bot_connected: boolean;
        bot_username: string | null;
        webhook_registered: boolean;
        user: {
            id: number;
            name: string;
            email: string | null;
            avatar: string | null;
            joined: string;
            on_telegram: boolean;
        };
        revenue: number;
        counts: {
            clients: number;
            programs: number;
            meal_programs: number;
            broadcasts: number;
        };
    };
    clients: Client[];
    programs: Plan[];
    mealPrograms: Plan[];
    broadcasts: Broadcast[];
};

export default function AdminTrainerShow({
    trainer,
    clients,
    programs,
    mealPrograms,
    broadcasts,
}: Props) {
    const { confirm, confirmDialog } = useConfirm();

    return (
        <>
            <Head title={`Trainer — ${trainer.user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={trainer.user.name}
                    description={`${trainer.slug} · joined ${trainer.user.joined}`}
                    actions={
                        <>
                            {trainer.bot_connected && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        confirm({
                                            title: `Disconnect @${trainer.bot_username}?`,
                                            description:
                                                'The webhook is deleted and the token cleared — the trainer must reconnect their bot.',
                                            confirmLabel: 'Suspend bot',
                                            destructive: true,
                                            onConfirm: () =>
                                                router.post(
                                                    suspendBot(trainer.id).url,
                                                ),
                                        })
                                    }
                                >
                                    <Unplug className="size-4" />
                                    Suspend bot
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    confirm({
                                        title: `Demote ${trainer.user.name}?`,
                                        description:
                                            'They lose trainer access but keep their tenant data and plans.',
                                        confirmLabel: 'Demote',
                                        destructive: true,
                                        onConfirm: () =>
                                            router.post(
                                                demote(trainer.id).url,
                                            ),
                                    })
                                }
                            >
                                <ArrowDownCircle className="size-4" />
                                Demote
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    confirm({
                                        title: `Delete trainer ${trainer.user.name}?`,
                                        description:
                                            'Removes the tenant and client links. Their user account and programs stay.',
                                        confirmLabel: 'Delete',
                                        destructive: true,
                                        onConfirm: () =>
                                            router.delete(
                                                destroy(trainer.id).url,
                                            ),
                                    })
                                }
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-4 pt-6">
                            <Avatar className="size-14">
                                <AvatarImage
                                    src={trainer.user.avatar ?? undefined}
                                />
                                <AvatarFallback className="text-lg">
                                    {trainer.user.name.slice(0, 1)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid gap-1 text-sm">
                                <p className="font-medium">
                                    {trainer.user.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {trainer.user.email ?? '—'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {trainer.user.on_telegram
                                        ? 'Telegram linked'
                                        : 'Not on Telegram'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <Bot className="size-4" /> Bot
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {trainer.bot_connected ? (
                                <>
                                    <p>
                                        <b>@{trainer.bot_username}</b>{' '}
                                        connected
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        webhook{' '}
                                        {trainer.webhook_registered
                                            ? 'registered'
                                            : 'not registered'}
                                    </p>
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    No bot connected.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <Wallet className="size-4" /> Revenue
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p className="text-lg font-bold">
                                {trainer.revenue.toLocaleString()} ETB
                            </p>
                            <p className="text-xs text-muted-foreground">
                                from successful plan sales
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {(
                        [
                            ['Clients', trainer.counts.clients, Users],
                            [
                                'Programs',
                                trainer.counts.programs,
                                Dumbbell,
                            ],
                            [
                                'Meal plans',
                                trainer.counts.meal_programs,
                                UtensilsCrossed,
                            ],
                            [
                                'Broadcasts',
                                trainer.counts.broadcasts,
                                Megaphone,
                            ],
                        ] as const
                    ).map(([label, count, Icon]) => (
                        <Card key={label}>
                            <CardContent className="flex items-center gap-3 pt-6">
                                <Icon className="size-5 text-muted-foreground" />
                                <div>
                                    <p className="text-2xl font-bold tabular-nums">
                                        {count}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Recent clients
                            </CardTitle>
                            <CardDescription>
                                Invite link: {trainer.invite_url}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-1.5">
                            {clients.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No clients yet.
                                </p>
                            )}
                            {clients.map((client) => (
                                <div
                                    key={client.id}
                                    className="flex items-center gap-3 rounded-md border px-3 py-2 text-sm"
                                >
                                    <Avatar className="size-7">
                                        <AvatarImage
                                            src={client.avatar ?? undefined}
                                        />
                                        <AvatarFallback>
                                            {client.name.slice(0, 1)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="flex-1">
                                        {client.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {client.joined ?? '—'}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Plans
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-1.5">
                            {programs.length === 0 &&
                                mealPrograms.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No plans built yet.
                                    </p>
                                )}
                            {programs.map((plan) => (
                                <div
                                    key={plan.id}
                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                >
                                    <span className="flex items-center gap-2">
                                        <Dumbbell className="size-3.5 text-muted-foreground" />
                                        {plan.name}
                                    </span>
                                    <Badge
                                        variant={
                                            plan.is_free
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {plan.is_free
                                            ? 'Free'
                                            : `${plan.price_etb.toLocaleString()} ETB`}
                                    </Badge>
                                </div>
                            ))}
                            {mealPrograms.map((plan) => (
                                <div
                                    key={`m-${plan.id}`}
                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                >
                                    <span className="flex items-center gap-2">
                                        <UtensilsCrossed className="size-3.5 text-muted-foreground" />
                                        {plan.name}
                                    </span>
                                    <Badge
                                        variant={
                                            plan.is_free
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {plan.is_free
                                            ? 'Free'
                                            : `${plan.price_etb.toLocaleString()} ETB`}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent broadcasts
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-1.5">
                        {broadcasts.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No broadcasts sent.
                            </p>
                        )}
                        {broadcasts.map((broadcast) => (
                            <div
                                key={broadcast.id}
                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                            >
                                <span className="max-w-md truncate">
                                    {broadcast.body}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {broadcast.sent_count} sent ·{' '}
                                    {broadcast.created_at}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
            {confirmDialog}
        </>
    );
}

AdminTrainerShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Trainers', href: trainersIndex() },
    ],
};
