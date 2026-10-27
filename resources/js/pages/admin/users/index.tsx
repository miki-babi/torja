import { Head, Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Search } from 'lucide-react';
import { FormEvent } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import AlertError from '@/components/alert-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { destroy, index, show, update } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

type Props = {
    users: Paginated<AdminUser>;
    filters: { search: string };
};

function userLabel(user: AdminUser) {
    return user.email ?? user.phone ?? user.username ?? `#${user.id}`;
}

export default function AdminUsersIndex({ users, filters }: Props) {
    const { errors } = usePage().props;
    const { confirm, confirmDialog } = useConfirm();

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            index().url,
            { search: data.get('search') },
            { preserveState: true },
        );
    }

    function toggleRole(user: AdminUser) {
        const role = user.role === 'admin' ? 'user' : 'admin';
        confirm({
            title: `Change role to "${role}"?`,
            description: `${userLabel(user)} will ${role === 'admin' ? 'gain full admin access' : 'lose admin access'}.`,
            confirmLabel: 'Change role',
            onConfirm: () =>
                router.put(
                    update.url(user.id),
                    { role },
                    { preserveScroll: true },
                ),
        });
    }

    function deleteUser(user: AdminUser) {
        confirm({
            title: `Delete ${userLabel(user)}?`,
            description:
                'This permanently removes the account and cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
            onConfirm: () => router.delete(destroy.url(user.id)),
        });
    }

    return (
        <>
            <Head title="Admin — users" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Users"
                    description="Manage accounts and roles"
                />

                {Object.keys(errors).length > 0 && (
                    <AlertError errors={Object.values(errors)} />
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All users</CardTitle>
                        <CardDescription>
                            {users.total.toLocaleString()} accounts — search by
                            name, email, phone, or username
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <div className="relative max-w-xs flex-1">
                                <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search users…"
                                    className="pl-8"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>

                        {users.data.length === 0 ? (
                            <EmptyState
                                title="No users found"
                                description="Try a different search term."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Paid</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarFallback className="text-xs">
                                                            {(user.name ?? '?')
                                                                .slice(0, 2)
                                                                .toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {user.name}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {[
                                                                user.email,
                                                                user.phone,
                                                                user.username
                                                                    ? `@${user.username}`
                                                                    : null,
                                                                user.telegram_username
                                                                    ? `tg: @${user.telegram_username}`
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        user.role === 'admin'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {user.role}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {(user.successful_payments_count ??
                                                    0) > 0 ? (
                                                    <Badge variant="outline">
                                                        paid
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Actions for ${user.name}`}
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={show(
                                                                    user.id,
                                                                )}
                                                            >
                                                                View details
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                toggleRole(user)
                                                            }
                                                        >
                                                            {user.role ===
                                                            'admin'
                                                                ? 'Demote to user'
                                                                : 'Promote to admin'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                deleteUser(user)
                                                            }
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <AdminPagination page={users} />
                    </CardContent>
                </Card>
            </div>
            {confirmDialog}
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Users', href: index() },
    ],
};
