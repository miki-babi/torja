import { Head, router } from '@inertiajs/react';
import { ArrowRight, Infinity as InfinityIcon, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    JamiPaymentOverlay,
    type PendingJamiPayment,
} from '@/components/plan/jami-payment-overlay';
import {
    iconFor,
    type ProCopy,
    ProScreen,
} from '@/components/pro/pro-screen';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Provider = 'chapa' | 'verify_checkout' | 'jami';

type Props = {
    isPro: boolean;
    price: number;
    page: ProCopy;
    homeUrl: string;
    providers: Record<Provider, boolean>;
    pendingJamiPayment: PendingJamiPayment | null;
    checkoutUrl: string;
};

export default function Pro({
    isPro,
    price,
    page,
    homeUrl,
    providers,
    pendingJamiPayment,
    checkoutUrl,
}: Props) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [gateway, setGateway] = useState('telebirr');
    const [phone, setPhone] = useState('');

    const enabled = (Object.keys(providers) as Provider[]).filter(
        (provider) => providers[provider],
    );
    const primary: Provider | null =
        enabled.length === 1 ? enabled[0] : null;

    function pay(
        provider: Provider,
        extra: { gateway?: string; customer_phone?: string } = {},
    ) {
        router.post(checkoutUrl, { provider, ...extra });
    }

    function close() {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
        router.visit(homeUrl);
    }

    function continueFree() {
        if (page.free_notice !== '') {
            toast.info(page.free_notice);
        }
        close();
    }

    return (
        <>
            <Head title={page.sheet_title} />
            {pendingJamiPayment !== null && (
                <JamiPaymentOverlay payment={pendingJamiPayment} />
            )}
            <MobileShell hideNav>
                <ProScreen
                    copy={page}
                    isPro={isPro}
                    onClose={close}
                    onFree={continueFree}
                    onUnlock={() => setSheetOpen(true)}
                />
            </MobileShell>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent
                    side="bottom"
                    className="mx-auto max-h-[90vh] max-w-md overflow-y-auto rounded-t-3xl border-[#2E2E2E] bg-[#141414] px-5 pb-8 text-[#F5F5F5]"
                >
                    <div className="mx-auto mt-1 h-1 w-10 rounded-full bg-[#2E2E2E]" />
                    <SheetHeader className="items-center px-0 text-center">
                        <SheetTitle className="text-lg">
                            {page.sheet_title}
                        </SheetTitle>
                        <SheetDescription className="text-[#8E8E93]">
                            {page.sheet_subtitle}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="rounded-2xl border border-[#AFFA01]/40 bg-[#1F1F1F] p-4 shadow-[0_0_0_4px_rgba(175,250,1,0.08)]">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-3xl font-extrabold tracking-tight">
                                    {price.toLocaleString()}{' '}
                                    <span className="text-base font-bold">
                                        ETB
                                    </span>
                                </p>
                                <p className="text-sm text-[#8E8E93]">
                                    {page.price_label}
                                </p>
                            </div>
                            {page.price_pill !== '' && (
                                <span className="flex items-center gap-1 rounded-full bg-[#AFFA01]/15 px-2.5 py-1 text-xs font-bold text-[#AFFA01]">
                                    <InfinityIcon className="size-3.5" />
                                    {page.price_pill}
                                </span>
                            )}
                        </div>
                    </div>

                    {page.trust_badges.length > 0 && (
                        <div className="mt-4 grid grid-cols-3 gap-2 text-center text-[11px] text-[#8E8E93]">
                            {page.trust_badges.map((badge, index) => {
                                const Icon = iconFor(badge.icon);
                                return (
                                    <div
                                        key={index}
                                        className="flex flex-col items-center gap-1"
                                    >
                                        <Icon className="size-4 text-[#AFFA01]" />
                                        <span>{badge.text}</span>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <div className="mt-5 space-y-2">
                        {primary !== null ? (
                            <Button
                                onClick={() => pay(primary)}
                                className="h-12 w-full rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-sm font-extrabold text-black hover:opacity-90"
                            >
                                {page.sheet_button}
                                <ArrowRight className="size-4" />
                            </Button>
                        ) : (
                            <>
                                {providers.jami && (
                                    <Button
                                        onClick={() => pay('jami')}
                                        className="h-12 w-full rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-sm font-extrabold text-black hover:opacity-90"
                                    >
                                        Pay with Jami
                                        <ArrowRight className="size-4" />
                                    </Button>
                                )}
                                {providers.chapa && (
                                    <Button
                                        onClick={() => pay('chapa')}
                                        variant="outline"
                                        className="h-12 w-full rounded-full"
                                    >
                                        Pay with Chapa
                                    </Button>
                                )}
                                {providers.verify_checkout && (
                                    <Button
                                        onClick={() => pay('verify_checkout')}
                                        variant="outline"
                                        className="h-12 w-full rounded-full"
                                    >
                                        Pay with Verify
                                    </Button>
                                )}
                            </>
                        )}
                        {enabled.length === 0 && (
                            <p className="text-center text-sm text-[#8E8E93]">
                                Payments are not available right now.
                            </p>
                        )}
                    </div>

                    {providers.jami && (
                        <>
                            <div className="my-4 flex items-center gap-3">
                                <Separator className="flex-1 bg-[#2E2E2E]" />
                                <span className="text-xs text-[#8E8E93]">
                                    or push to your phone
                                </span>
                                <Separator className="flex-1 bg-[#2E2E2E]" />
                            </div>

                            <div className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="jami-gateway">
                                        Payment method
                                    </Label>
                                    <Select
                                        value={gateway}
                                        onValueChange={setGateway}
                                    >
                                        <SelectTrigger
                                            id="jami-gateway"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="telebirr">
                                                Telebirr
                                            </SelectItem>
                                            <SelectItem value="mpesa">
                                                M-Pesa
                                            </SelectItem>
                                            <SelectItem value="cbe">
                                                CBE Birr
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="jami-phone">
                                        Phone number
                                    </Label>
                                    <Input
                                        id="jami-phone"
                                        type="tel"
                                        inputMode="tel"
                                        placeholder="0911 223344"
                                        value={phone}
                                        onChange={(e) =>
                                            setPhone(e.target.value)
                                        }
                                    />
                                </div>

                                <Button
                                    variant="outline"
                                    className="h-11 w-full rounded-full"
                                    disabled={
                                        phone.replace(/\D/g, '').length < 9
                                    }
                                    onClick={() =>
                                        pay('jami', {
                                            gateway,
                                            customer_phone: phone.replace(
                                                /\s/g,
                                                '',
                                            ),
                                        })
                                    }
                                >
                                    <Smartphone className="size-4" />
                                    Push payment to my phone
                                </Button>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
