import { Head, Link, router, usePage } from '@inertiajs/react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaymentStatusBadge } from '@/components/admin/payment-status-badge';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { show as showPayment } from '@/routes/admin/payments';
import { destroy, index, update } from '@/routes/admin/users';
import {
    destroy as revokeProGrant,
    store as grantPro,
} from '@/routes/admin/users/pro-grant';
import type { AdminUser } from '@/types';

type ActiveProGrant = {
    id: number;
    source: string;
    referral_count: number | null;
    granter_name: string | null;
    created_at: string;
};

type Props = {
    user: AdminUser;
    proType: 'paid' | 'granted' | null;
    activeProGrant: ActiveProGrant | null;
};

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}

export default function AdminUserShow({
    user,
    proType,
    activeProGrant,
}: Props) {
    const { auth, errors } = usePage().props;
    const { confirm, confirmDialog } = useConfirm();
    const isSelf = auth.user.id === user.id;
    const profile = user.nutrition_profile ?? null;

    const label = user.email ?? user.phone ?? user.username ?? `#${user.id}`;

    function toggleRole() {
        const role = user.role === 'admin' ? 'user' : 'admin';
        confirm({
            title: `Change role to "${role}"?`,
            description: `${label} will ${role === 'admin' ? 'gain full admin access' : 'lose admin access'}.`,
            confirmLabel: 'Change role',
            onConfirm: () =>
                router.put(
                    update.url(user.id),
                    { role },
                    { preserveScroll: true },
                ),
        });
    }

    function deleteUser() {
        confirm({
            title: `Delete ${label}?`,
            description:
                'This permanently removes the account and cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
            onConfirm: () => router.delete(destroy.url(user.id)),
        });
    }

    function grantProToUser() {
        confirm({
            title: `Grant Pro to ${label}?`,
            description:
                'They get full Pro access without paying. No commission is created.',
            confirmLabel: 'Grant Pro',
            onConfirm: () =>
                router.post(grantPro.url(user.id), {}, { preserveScroll: true }),
        });
    }

    function revokeProFromUser() {
        confirm({
            title: `Revoke granted Pro from ${label}?`,
            description:
                'Their free Pro access ends immediately. Paid Pro, if any, is unaffected.',
            confirmLabel: 'Revoke Pro',
            destructive: true,
            onConfirm: () =>
                router.delete(revokeProGrant.url(user.id), {
                    preserveScroll: true,
                }),
        });
    }

    return (
        <>
            <Head title={`Admin — ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={user.name}
                    description={label}
                    actions={
                        <Badge
                            variant={
                                user.role === 'admin' ? 'default' : 'secondary'
                            }
                            className="text-xs"
                        >
                            {user.role}
                        </Badge>
                    }
                />

                {Object.keys(errors).length > 0 && (
                    <AlertError errors={Object.values(errors)} />
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-center gap-3">
                                <Avatar className="size-11">
                                    <AvatarFallback>
                                        {(user.name ?? '?')
                                            .slice(0, 2)
                                            .toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0">
                                    <CardTitle className="truncate text-base">
                                        {user.name}
                                    </CardTitle>
                                    <CardDescription className="truncate">
                                        {label}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm">
                                    <Field label="Joined">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </Field>
                                    <Field label="Username">
                                        {user.username
                                            ? `@${user.username}`
                                            : '—'}
                                    </Field>
                                    <Field label="Phone">
                                        {user.phone ?? '—'}
                                    </Field>
                                    <Field label="Telegram">
                                        {user.telegram_username
                                            ? `@${user.telegram_username}`
                                            : '—'}
                                    </Field>
                                    <Field label="Email verified">
                                        {user.email_verified_at ? 'Yes' : 'No'}
                                    </Field>
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Pro membership
                                </CardTitle>
                                <CardDescription>
                                    {proType === 'paid' &&
                                        'Paid — from a successful Pro purchase'}
                                    {proType === 'granted' &&
                                        'Granted — earned or given, no payment'}
                                    {proType === null && 'No Pro access'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            proType === null
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {proType === null
                                            ? 'Free'
                                            : `Pro · ${proType}`}
                                    </Badge>
                                </div>
                                {activeProGrant !== null && (
                                    <dl className="grid gap-2 text-xs text-muted-foreground">
                                        <Field label="Source">
                                            {activeProGrant.source ===
                                            'referral_reward'
                                                ? `Referral reward (${activeProGrant.referral_count ?? '?'} referrals)`
                                                : `Manual${
                                                      activeProGrant.granter_name !==
                                                      null
                                                          ? ` by ${activeProGrant.granter_name}`
                                                          : ''
                                                  }`}
                                        </Field>
                                        <Field label="Granted">
                                            {new Date(
                                                activeProGrant.created_at,
                                            ).toLocaleDateString()}
                                        </Field>
                                    </dl>
                                )}
                                <div className="flex flex-col gap-2">
                                    {proType === null && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full justify-start"
                                            onClick={grantProToUser}
                                        >
                                            Grant Pro
                                        </Button>
                                    )}
                                    {activeProGrant !== null && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full justify-start text-destructive"
                                            onClick={revokeProFromUser}
                                        >
                                            Revoke granted Pro
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-destructive/40">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Actions
                                </CardTitle>
                                <CardDescription>
                                    Administrative operations on this account
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full justify-start"
                                    onClick={toggleRole}
                                    disabled={isSelf}
                                >
                                    {user.role === 'admin'
                                        ? 'Demote to user'
                                        : 'Promote to admin'}
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    className="w-full justify-start"
                                    onClick={deleteUser}
                                    disabled={isSelf}
                                >
                                    Delete account
                                </Button>
                                {isSelf && (
                                    <p className="text-xs text-muted-foreground">
                                        You can't change your own role or delete
                                        your own account here.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Nutrition profile
                                </CardTitle>
                                <CardDescription>
                                    Wizard answers and completion status
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="text-sm">
                                {profile === null ? (
                                    <p className="text-muted-foreground">
                                        No nutrition profile.
                                    </p>
                                ) : (
                                    <dl className="grid gap-3 sm:grid-cols-3">
                                        <Field label="Status">
                                            <Badge variant="outline">
                                                {profile.status}
                                            </Badge>
                                        </Field>
                                        <Field label="Completed">
                                            {profile.completed_at
                                                ? new Date(
                                                      profile.completed_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </Field>
                                        <Field label="Goal">
                                            {typeof profile.goal
                                                ?.primary_goal === 'string'
                                                ? profile.goal.primary_goal
                                                : '—'}
                                        </Field>
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Payments
                                </CardTitle>
                                <CardDescription>
                                    All payment attempts for this account
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {(user.payments ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No payments.
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>tx_ref</TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead className="w-16" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {user.payments!.map((payment) => (
                                                <TableRow key={payment.id}>
                                                    <TableCell className="max-w-48 truncate font-mono text-xs">
                                                        {payment.tx_ref}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {payment.amount}{' '}
                                                        {payment.currency}
                                                    </TableCell>
                                                    <TableCell>
                                                        <PaymentStatusBadge
                                                            status={
                                                                payment.status
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {new Date(
                                                            payment.created_at,
                                                        ).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={showPayment(
                                                                    payment.id,
                                                                )}
                                                                prefetch
                                                            >
                                                                View
                                                            </Link>
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            {confirmDialog}
        </>
    );
}

AdminUserShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Users', href: index() },
    ],
};
