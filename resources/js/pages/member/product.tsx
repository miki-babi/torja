import { Head, Link, router, usePage } from '@inertiajs/react';
import { Minus, Package, Plus, Truck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MobileShell } from '@/components/member/mobile-shell';
import { OrderSuccessDialog } from '@/components/member/order-success-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

type Product = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    price_etb: number;
    content: string | null;
};

export default function ProductShow({
    product,
    memberPhone,
    storeUrl,
    loginUrl,
}: {
    product: Product;
    memberPhone: string | null;
    storeUrl: string;
    loginUrl: string;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const isGuest = auth.user === null;

    const [quantity, setQuantity] = useState(1);
    const [phone, setPhone] = useState(memberPhone ?? '');
    const [busy, setBusy] = useState(false);
    const [placed, setPlaced] = useState(false);

    const valid = phone.trim().length >= 9;
    const total = product.price_etb * quantity;

    function placeOrder() {
        if (busy || !valid) {
            return;
        }
        setBusy(true);
        router.post(
            storeUrl,
            {
                product_id: product.id,
                quantity,
                phone: phone.trim(),
            },
            {
                onSuccess: () => {
                    setPlaced(true);
                    setBusy(false);
                },
                onError: () => {
                    toast.error(t('shop.order_failed'));
                    setBusy(false);
                },
            },
        );
    }

    return (
        <MobileShell>
            <Head title={product.name} />

            <div className="mt-5 overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#141414]">
                <div className="flex aspect-[4/3] w-full items-center justify-center bg-[#1F1F1F]">
                    {product.image_url !== null ? (
                        <img
                            src={product.image_url}
                            alt={product.name}
                            className="size-full object-cover"
                        />
                    ) : (
                        <Package className="size-12 text-[#AFFA01]" />
                    )}
                </div>
                <div className="p-4">
                    <h1 className="text-lg font-extrabold text-[#F5F5F5]">
                        {product.name}
                    </h1>
                    {product.description !== null && (
                        <p className="mt-1 text-sm leading-relaxed text-[#8E8E93]">
                            {product.description}
                        </p>
                    )}
                    <p className="mt-2 text-base font-bold text-[#AFFA01]">
                        {product.price_etb.toLocaleString()} ETB
                    </p>
                    {product.content !== null && (
                        <div
                            className="mt-4 space-y-3 border-t border-[#2E2E2E] pt-4 text-sm leading-relaxed text-[#8E8E93] [&_a]:text-[#AFFA01] [&_a]:underline [&_figcaption]:mt-1.5 [&_figcaption]:text-center [&_figcaption]:text-xs [&_figcaption]:text-[#8E8E93] [&_figure]:mx-auto [&_figure]:my-3 [&_h2]:text-base [&_h2]:font-extrabold [&_h2]:text-[#F5F5F5] [&_h3]:text-sm [&_h3]:font-bold [&_h3]:text-[#F5F5F5] [&_img]:w-full [&_img]:rounded-xl [&_ol]:list-decimal [&_ol]:pl-5 [&_s]:line-through [&_ul]:list-disc [&_ul]:pl-5"
                            dangerouslySetInnerHTML={{
                                __html: product.content,
                            }}
                        />
                    )}
                </div>
            </div>

            {isGuest ? (
                <Button
                    asChild
                    className="mt-5 w-full rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-6 text-sm font-extrabold text-black hover:opacity-90"
                >
                    <Link href={loginUrl}>{t('shop.login_to_order')}</Link>
                </Button>
            ) : (
                <>
                    <div className="mt-4 space-y-4 rounded-2xl border border-[#2E2E2E] bg-[#141414] p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-[#F5F5F5]">
                                {t('shop.quantity')}
                            </span>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    aria-label="Decrease"
                                    disabled={quantity <= 1}
                                    onClick={() =>
                                        setQuantity((n) => Math.max(1, n - 1))
                                    }
                                    className="flex size-9 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F] text-[#F5F5F5] disabled:opacity-40"
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="w-7 text-center text-base font-bold">
                                    {quantity}
                                </span>
                                <button
                                    type="button"
                                    aria-label="Increase"
                                    disabled={quantity >= 10}
                                    onClick={() =>
                                        setQuantity((n) => Math.min(10, n + 1))
                                    }
                                    className="flex size-9 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F] text-[#F5F5F5] disabled:opacity-40"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="phone"
                                className="text-sm font-semibold text-[#F5F5F5]"
                            >
                                {t('shop.phone')}
                            </label>
                            <Input
                                id="phone"
                                value={phone}
                                onChange={(event) =>
                                    setPhone(event.target.value)
                                }
                                placeholder="09..."
                                inputMode="tel"
                                className="mt-1.5"
                            />
                            <p className="mt-1 text-[11px] text-[#8E8E93]">
                                {t('shop.cod_hint')}
                            </p>
                        </div>

                        <div className="flex items-center gap-2.5 rounded-xl bg-[#AFFA01]/10 px-3 py-2.5">
                            <Truck className="size-4 shrink-0 text-[#AFFA01]" />
                            <p className="text-xs font-semibold text-[#AFFA01]">
                                {t('shop.free_delivery')}
                            </p>
                        </div>

                        <div className="flex items-center justify-between border-t border-[#2E2E2E] pt-3">
                            <span className="text-sm font-semibold text-[#8E8E93]">
                                {t('shop.total')}
                            </span>
                            <span className="text-lg font-extrabold text-[#AFFA01]">
                                {total.toLocaleString()} ETB
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={placeOrder}
                        disabled={busy || !valid}
                        className="mt-4 w-full rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-3 text-sm font-extrabold text-black shadow-md transition-transform active:scale-[0.98] disabled:opacity-50"
                    >
                        {t('shop.place_order')}
                    </button>
                </>
            )}

            <OrderSuccessDialog open={placed} onOpenChange={setPlaced} />
        </MobileShell>
    );
}
