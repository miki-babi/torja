import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    Circle,
    Copy,
    Dumbbell,
    Megaphone,
    Users,
    UtensilsCrossed,
    Wallet,
} from 'lucide-react';
import { toast } from 'sonner';
import { AdminPageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
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
import { dashboard } from '@/routes/trainer';
import { edit as botEdit } from '@/routes/trainer/bot';
import { index as clientsIndex, show as clientShow } from '@/routes/trainer/clients';
import { create as createProgram } from '@/routes/trainer/programs';
import { create as createMealProgram } from '@/routes/trainer/meal-programs';

type Client = {
    id: number;
    name: string;
    username: string | null;
    avatar: string | null;
    joined: string | null;
};

type Props = {
    trainer: {
        display_name: string;
        slug: string;
        invite_url: string;
        bot_connected: boolean;
        bot_username: string | null;
        webhook_registered: boolean;
    };
    stats: {
        clients: number;
        programs: number;
        meal_programs: number;
        broadcasts: number;
        revenue: number;
    };
    recentClients: Client[];
};

export default function TrainerDashboard({
    trainer,
    stats,
    recentClients,
}: Props) {
    const [, copy] = useClipboard();

    const checklist = [
        {
            done: trainer.bot_connected,
            label: 'Connect your Telegram bot',
            href: botEdit(),
        },
        {
            done: stats.clients > 0,
            label: 'Invite your first client',
            href: clientsIndex(),
        },
        {
            done: stats.programs > 0,
            label: 'Build a workout program',
            href: createProgram(),
        },
        {
            done: stats.meal_programs > 0,
            label: 'Build a meal program',
            href: createMealProgram(),
        },
    ];
    const allDone = checklist.every((step) => step.done);

    return (
        <>
            <Head title="Coach dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={`Welcome, ${trainer.display_name}`}
                    description={
                        trainer.bot_connected
                            ? `@${trainer.bot_username} is live for your clients.`
                            : 'Connect your Telegram bot to reach clients.'
                    }
                />

                {!allDone && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Get set up
                            </CardTitle>
                            <CardDescription>
                                A few steps before clients can train with you.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            {checklist.map((step) => (
                                <Link
                                    key={step.label}
                                    href={step.href}
                                    className="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                >
                                    {step.done ? (
                                        <CheckCircle2 className="size-4 text-lime-600" />
                                    ) : (
                                        <Circle className="size-4 text-muted-foreground" />
                                    )}
                                    <span
                                        className={
                                            step.done
                                                ? 'text-muted-foreground line-through'
                                                : ''
                                        }
                                    >
                                        {step.label}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Clients"
                        value={stats.clients}
                        icon={Users}
                    />
                    <StatCard
                        label="Programs"
                        value={stats.programs}
                        icon={Dumbbell}
                    />
                    <StatCard
                        label="Meal programs"
                        value={stats.meal_programs}
                        icon={UtensilsCrossed}
                    />
                    <StatCard
                        label="Plan revenue"
                        value={`${stats.revenue.toLocaleString()} ETB`}
                        icon={Wallet}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Invite link
                            </CardTitle>
                            <CardDescription>
                                Share this with clients — it also powers your
                                bot's Open App button.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex items-center gap-2">
                            <code className="flex-1 truncate rounded-md bg-muted px-3 py-2 text-xs">
                                {trainer.invite_url}
                            </code>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={async () => {
                                    if (await copy(trainer.invite_url)) {
                                        toast.success('Link copied');
                                    }
                                }}
                            >
                                <Copy className="size-4" />
                                Copy
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">
                                Recent clients
                            </CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={clientsIndex()}>
                                    View all
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="grid gap-1">
                            {recentClients.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No clients yet — share your invite link.
                                </p>
                            )}
                            {recentClients.map((client) => (
                                <Link
                                    key={client.id}
                                    href={clientShow(client.id).url}
                                    className="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted"
                                >
                                    <Avatar className="size-8">
                                        <AvatarImage
                                            src={client.avatar ?? undefined}
                                        />
                                        <AvatarFallback>
                                            {client.name.slice(0, 1)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {client.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            joined {client.joined ?? 'recently'}
                                        </p>
                                    </div>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

TrainerDashboard.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
