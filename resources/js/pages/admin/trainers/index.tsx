import { Form, Head, Link, router } from '@inertiajs/react';
import { Bot, Search, Users, Wallet } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
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
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import {
    index,
    promote,
    show,
} from '@/routes/admin/trainers';
import type { Paginated } from '@/types';

type Trainer = {
    id: number;
    name: string;
    slug: string;
    avatar: string | null;
    email: string | null;
    bot_username: string | null;
    bot_connected: boolean;
    clients_count: number;
    programs_count: number;
    meal_programs_count: number;
    revenue: number;
    joined: string;
};

type Props = {
    trainers: Paginated<Trainer>;
    filters: { search: string };
    stats: { trainers: number; clients: number; revenue: number };
};

export default function AdminTrainersIndex({
    trainers,
    filters,
    stats,
}: Props) {
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
            <Head title="Trainers" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Trainers"
                    description="Coach tenants on the platform."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Trainers" value={stats.trainers} icon={Users} />
                    <StatCard
                        label="Coached clients"
                        value={stats.clients}
                        icon={Users}
                    />
                    <StatCard
                        label="Coach plan revenue"
                        value={`${stats.revenue.toLocaleString()} ETB`}
                        icon={Wallet}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Promote a member
                        </CardTitle>
                        <CardDescription>
                            Enter an existing member's email to give them a
                            coach workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...promote.form()}
                            className="flex max-w-md items-start gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex-1">
                                        <Input
                                            name="email"
                                            type="email"
                                            placeholder="member@example.com"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Promote to trainer
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle className="text-base">
                                All trainers
                            </CardTitle>
                            <CardDescription>
                                {trainers.total} trainer
                                {trainers.total === 1 ? '' : 's'}
                            </CardDescription>
                        </div>
                        <form onSubmit={submitSearch} className="relative">
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search trainers…"
                                className="w-56 pl-8"
                            />
                        </form>
                    </CardHeader>
                    <CardContent>
                        {trainers.data.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="No trainers yet"
                                description="Promote a member above or share /trainer/register."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Trainer</TableHead>
                                        <TableHead>Bot</TableHead>
                                        <TableHead className="text-right">
                                            Clients
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Programs
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Meals
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead>Joined</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {trainers.data.map((trainer) => (
                                        <TableRow key={trainer.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(trainer.id).url}
                                                    className="flex items-center gap-3 hover:underline"
                                                >
                                                    <Avatar className="size-8">
                                                        <AvatarImage
                                                            src={
                                                                trainer.avatar ??
                                                                undefined
                                                            }
                                                        />
                                                        <AvatarFallback>
                                                            {trainer.name.slice(
                                                                0,
                                                                1,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {trainer.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {trainer.email ??
                                                                trainer.slug}
                                                        </p>
                                                    </div>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {trainer.bot_connected ? (
                                                    <Badge variant="default">
                                                        <Bot className="mr-1 size-3" />
                                                        @{trainer.bot_username}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        not connected
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {trainer.clients_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {trainer.programs_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {trainer.meal_programs_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {trainer.revenue.toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {trainer.joined}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <div className="mt-4">
                            <AdminPagination page={trainers} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminTrainersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Trainers', href: '/admin/trainers' },
    ],
};
