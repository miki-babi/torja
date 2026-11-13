import { Head, router } from '@inertiajs/react';
import { Banknote, Check, Wallet } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { sent, store } from '@/routes/admin/payouts';
import type { Paginated } from '@/types';

type Balance = {
    id: number;
    name: string;
    avatar: string | null;
    payout_method: string | null;
    payout_account: string | null;
    unpaid_total: number;
};

type Payout = {
    id: number;
    trainer: string;
    avatar: string | null;
    amount: number;
    status: string;
    earnings_count: number;
    processor: string | null;
    sent_at: string | null;
    created_at: string;
};

type Props = {
    balances: Balance[];
    payouts: Paginated<Payout>;
};

export default function AdminPayoutsIndex({ balances, payouts }: Props) {
    return (
        <>
            <Head title="Payouts" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Payouts"
                    description="Create payout batches for trainers and mark them sent once money moves."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Unpaid balances
                        </CardTitle>
                        <CardDescription>
                            Trainers with earnings not yet in a payout batch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {balances.length === 0 ? (
                            <EmptyState
                                icon={Wallet}
                                title="Nothing to pay"
                                description="All trainer earnings are covered by payouts."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Trainer</TableHead>
                                        <TableHead>Payout details</TableHead>
                                        <TableHead className="text-right">
                                            Owed
                                        </TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {balances.map((balance) => (
                                        <TableRow key={balance.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarImage
                                                            src={
                                                                balance.avatar ??
                                                                undefined
                                                            }
                                                        />
                                                        <AvatarFallback>
                                                            {balance.name.slice(
                                                                0,
                                                                1,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="text-sm font-medium">
                                                        {balance.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {balance.payout_method ??
                                                balance.payout_account ? (
                                                    <>
                                                        {
                                                            balance.payout_method
                                                        }{' '}
                                                        {
                                                            balance.payout_account
                                                        }
                                                    </>
                                                ) : (
                                                    'not set'
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {balance.unpaid_total.toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            store(balance.id)
                                                                .url,
                                                        )
                                                    }
                                                >
                                                    <Banknote className="mr-1 size-4" />
                                                    Create payout
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Payout history
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {payouts.data.length === 0 ? (
                            <EmptyState
                                icon={Banknote}
                                title="No payouts yet"
                                description="Payout batches will appear here."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Trainer</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Earnings
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payouts.data.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell className="font-medium">
                                                {payout.trainer}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {payout.amount.toLocaleString()}{' '}
                                                ETB
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {payout.earnings_count}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        payout.status ===
                                                        'sent'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {payout.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {payout.created_at}
                                                {payout.processor
                                                    ? ` · by ${payout.processor}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {payout.status ===
                                                    'pending' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                sent(payout.id)
                                                                    .url,
                                                            )
                                                        }
                                                    >
                                                        <Check className="mr-1 size-4" />
                                                        Mark sent
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <AdminPagination paginated={payouts} />
            </div>
        </>
    );
}
