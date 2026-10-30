import { Head, router } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { MobileShell } from '@/components/member/mobile-shell';
import { cn } from '@/lib/utils';

type ShopOrder = {
    id: number;
    product_name: string | null;
    product_image: string | null;
    category: string | null;
    quantity: number;
    total_etb: number;
    phone: string;
    status: string;
    placed_at: string;
    can_cancel: boolean;
};

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-400/15 text-amber-400',
    confirmed: 'bg-sky-400/15 text-sky-400',
    on_transit: 'bg-violet-400/15 text-violet-400',
    delivered: 'bg-[#AFFA01]/15 text-[#AFFA01]',
    returned: 'bg-orange-400/15 text-orange-400',
    cancelled: 'bg-red-400/15 text-red-400',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    on_transit: 'On transit',
    delivered: 'Delivered',
    returned: 'Returned',
    cancelled: 'Cancelled',
};

export default function MemberOrders({ orders }: { orders: ShopOrder[] }) {
    return (
        <MobileShell>
            <Head title="My orders" />

            <h1 className="pt-2 text-xl font-bold tracking-tight text-[#F5F5F5]">
                My orders
            </h1>
            <p className="mt-1 text-xs text-[#8E8E93]">
                Pay in cash when your order arrives.
            </p>

            <div className="mt-5 space-y-3">
                {orders.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-[#2E2E2E] bg-[#141414] px-4 py-10 text-center">
                        <Package className="size-8 text-[#8E8E93]" />
                        <p className="text-sm text-[#8E8E93]">
                            No orders yet.
                        </p>
                    </div>
                ) : (
                    orders.map((order) => (
                        <div
                            key={order.id}
                            className="flex items-center gap-3 rounded-2xl border border-[#2E2E2E] bg-[#141414] p-3"
                        >
                            <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#1F1F1F]">
                                {order.product_image ? (
                                    <img
                                        src={order.product_image}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Package className="size-5 text-[#AFFA01]" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-bold text-[#F5F5F5]">
                                    {order.product_name}
                                </p>
                                <p className="text-xs text-[#8E8E93]">
                                    {order.quantity} × ·{' '}
                                    {order.total_etb.toLocaleString()} ETB ·{' '}
                                    {order.placed_at}
                                </p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1.5">
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                        STATUS_STYLES[order.status] ??
                                            'bg-[#1F1F1F] text-[#8E8E93]',
                                    )}
                                >
                                    {STATUS_LABELS[order.status] ??
                                        order.status}
                                </span>
                                {order.can_cancel && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                `/member/orders/${order.id}/cancel`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="text-[10px] font-semibold text-[#8E8E93] underline-offset-2 hover:text-red-400 hover:underline"
                                    >
                                        Cancel
                                    </button>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </MobileShell>
    );
}
