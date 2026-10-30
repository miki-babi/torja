import { Head, Link, router } from '@inertiajs/react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index, show, update } from '@/routes/admin/shop-orders';

type Order = {
    id: number;
    member: string;
    product: string;
    category: string | null;
    quantity: number;
    total_etb: number;
    phone: string;
    status: string;
    placed_at: string;
};

const STATUSES = [
    '',
    'pending',
    'confirmed',
    'on_transit',
    'delivered',
    'returned',
    'cancelled',
];

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-400/15 text-amber-500',
    confirmed: 'bg-sky-400/15 text-sky-500',
    on_transit: 'bg-violet-400/15 text-violet-400',
    delivered: 'bg-lime-400/15 text-lime-500',
    returned: 'bg-orange-400/15 text-orange-500',
    cancelled: 'bg-red-400/15 text-red-500',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    on_transit: 'On transit',
    delivered: 'Delivered',
    returned: 'Returned',
    cancelled: 'Cancelled',
};

function setStatus(order: Order, status: string) {
    router.put(
        update.url(order.id, { query: { status } }),
        {},
        { preserveScroll: true },
    );
}

export default function ShopOrdersIndex({
    orders,
    filters,
}: {
    orders: Order[];
    filters: { status: string };
}) {
    return (
        <>
            <Head title="Admin — shop orders" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Shop orders"
                    description="Cash-on-delivery orders placed from Explore."
                />

            <div className="flex items-center gap-2 overflow-x-auto pb-1">
                {STATUSES.map((status) => (
                    <button
                        key={status === '' ? 'all' : status}
                        type="button"
                        onClick={() =>
                            router.get(
                                index.url({
                                    query:
                                        status === ''
                                            ? {}
                                            : { status },
                                }),
                                {},
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                    only: ['orders', 'filters'],
                                },
                            )
                        }
                        className={cn(
                            'shrink-0 rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-wider',
                            filters.status === status
                                ? 'border-lime-400 bg-lime-400/15 text-lime-500'
                                : 'border-border bg-card text-muted-foreground',
                        )}
                    >
                        {status === ''
                            ? 'All'
                            : (STATUS_LABELS[status] ?? status)}
                    </button>
                ))}
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base">
                        {orders.length} order{orders.length === 1 ? '' : 's'}
                    </CardTitle>
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {orders.length === 0 && (
                        <p className="px-6 py-8 text-sm text-muted-foreground">
                            No orders.
                        </p>
                    )}
                    {orders.map((order) => (
                        <div
                            key={order.id}
                            className="flex flex-wrap items-center gap-3 px-6 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={show(order.id)}
                                    className="block truncate text-sm font-semibold hover:underline"
                                >
                                    {order.product} ×{order.quantity}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {order.member} · {order.phone} ·{' '}
                                    {order.placed_at}
                                </p>
                            </div>
                            <p className="text-sm font-bold">
                                {order.total_etb.toLocaleString()} ETB
                            </p>
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase',
                                    STATUS_STYLES[order.status],
                                )}
                            >
                                {STATUS_LABELS[order.status] ?? order.status}
                            </span>
                            <div className="flex gap-1.5">
                                {order.status === 'pending' && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatus(order, 'confirmed')
                                        }
                                        className="rounded-full bg-sky-400/15 px-3 py-1 text-xs font-bold text-sky-500 hover:bg-sky-400/25"
                                    >
                                        Confirm
                                    </button>
                                )}
                                {order.status === 'confirmed' && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatus(order, 'on_transit')
                                        }
                                        className="rounded-full bg-violet-400/15 px-3 py-1 text-xs font-bold text-violet-400 hover:bg-violet-400/25"
                                    >
                                        On transit
                                    </button>
                                )}
                                {(order.status === 'confirmed' ||
                                    order.status === 'on_transit') && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatus(order, 'delivered')
                                        }
                                        className="rounded-full bg-lime-400/15 px-3 py-1 text-xs font-bold text-lime-500 hover:bg-lime-400/25"
                                    >
                                        Deliver
                                    </button>
                                )}
                                {(order.status === 'pending' ||
                                    order.status === 'confirmed') && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatus(order, 'cancelled')
                                        }
                                        className="rounded-full bg-red-400/15 px-3 py-1 text-xs font-bold text-red-500 hover:bg-red-400/25"
                                    >
                                        Cancel
                                    </button>
                                )}
                                {(order.status === 'returned' ||
                                    order.status === 'cancelled') && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setStatus(order, 'pending')
                                        }
                                        className="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-bold text-amber-500 hover:bg-amber-400/25"
                                    >
                                        Re-open
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
            </div>
        </>
    );
}
