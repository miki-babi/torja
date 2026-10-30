import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Package, Truck, User } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/shop-orders';
import { show as userShow } from '@/routes/admin/users';

type Order = {
    id: number;
    member: { id: number | null; name: string; phone: string | null };
    product: {
        id: number | null;
        name: string;
        category: string | null;
        image_url: string | null;
    };
    quantity: number;
    unit_price_etb: number;
    total_etb: number;
    phone: string;
    status: string;
    placed_at: string;
    updated_at: string;
};

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

const TRANSITIONS: Record<string, { status: string; label: string; cls: string }[]> = {
    pending: [
        { status: 'confirmed', label: 'Confirm', cls: 'bg-sky-400/15 text-sky-500 hover:bg-sky-400/25' },
        { status: 'cancelled', label: 'Cancel order', cls: 'bg-red-400/15 text-red-500 hover:bg-red-400/25' },
    ],
    confirmed: [
        { status: 'on_transit', label: 'On transit', cls: 'bg-violet-400/15 text-violet-400 hover:bg-violet-400/25' },
        { status: 'delivered', label: 'Mark delivered', cls: 'bg-lime-400/15 text-lime-500 hover:bg-lime-400/25' },
        { status: 'cancelled', label: 'Cancel order', cls: 'bg-red-400/15 text-red-500 hover:bg-red-400/25' },
    ],
    on_transit: [
        { status: 'delivered', label: 'Mark delivered', cls: 'bg-lime-400/15 text-lime-500 hover:bg-lime-400/25' },
        { status: 'returned', label: 'Mark returned', cls: 'bg-orange-400/15 text-orange-500 hover:bg-orange-400/25' },
    ],
    returned: [
        { status: 'pending', label: 'Re-open', cls: 'bg-amber-400/15 text-amber-500 hover:bg-amber-400/25' },
    ],
    cancelled: [
        { status: 'pending', label: 'Re-open', cls: 'bg-amber-400/15 text-amber-500 hover:bg-amber-400/25' },
    ],
};

function setStatus(orderId: number, status: string) {
    router.put(update.url(orderId, { query: { status } }), {}, { preserveScroll: true });
}

export default function ShopOrderShow({ order }: { order: Order }) {
    const actions = TRANSITIONS[order.status] ?? [];

    return (
        <>
            <Head title={`Order #${order.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Link
                    href={index()}
                    className="flex w-fit items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Orders
                </Link>
                <AdminPageHeader
                    title={`Order #${order.id}`}
                    description={`Placed ${order.placed_at}`}
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Package className="size-4" /> Order
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center overflow-hidden rounded-xl bg-muted">
                                    {order.product.image_url ? (
                                        <img
                                            src={order.product.image_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <Package className="size-5 text-muted-foreground" />
                                    )}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">
                                        {order.product.name}
                                    </p>
                                    <p className="text-xs capitalize text-muted-foreground">
                                        {order.product.category}
                                    </p>
                                </div>
                            </div>
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Quantity
                                    </dt>
                                    <dd className="font-medium">
                                        ×{order.quantity}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Unit price
                                    </dt>
                                    <dd className="font-medium">
                                        {order.unit_price_etb.toLocaleString()}{' '}
                                        ETB
                                    </dd>
                                </div>
                                <div className="flex justify-between border-t pt-1.5">
                                    <dt className="font-semibold">Total</dt>
                                    <dd className="font-bold text-lime-500">
                                        {order.total_etb.toLocaleString()} ETB
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <User className="size-4" /> Member
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {order.member.id !== null ? (
                                <Link
                                    href={userShow(order.member.id)}
                                    className="font-semibold hover:underline"
                                >
                                    {order.member.name}
                                </Link>
                            ) : (
                                <p className="font-semibold">
                                    {order.member.name}
                                </p>
                            )}
                            <p className="text-muted-foreground">
                                Account phone: {order.member.phone ?? '—'}
                            </p>
                            <p className="text-muted-foreground">
                                Delivery phone: {order.phone}
                            </p>
                            <p className="flex items-center gap-1.5 text-muted-foreground">
                                <Truck className="size-4" /> Cash on delivery,
                                free delivery
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <span
                                className={cn(
                                    'inline-block rounded-full px-3 py-1 text-xs font-bold uppercase',
                                    STATUS_STYLES[order.status],
                                )}
                            >
                                {STATUS_LABELS[order.status] ??
                                    order.status}
                            </span>
                            {actions.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {actions.map((action) => (
                                        <button
                                            key={action.status}
                                            type="button"
                                            onClick={() =>
                                                setStatus(
                                                    order.id,
                                                    action.status,
                                                )
                                            }
                                            className={cn(
                                                'rounded-full px-3 py-1.5 text-xs font-bold transition-colors',
                                                action.cls,
                                            )}
                                        >
                                            {action.label}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    This order is closed.
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Last updated {order.updated_at}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
