import Heading from '@/components/heading';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    JamiPaymentOverlay,
    type PendingJamiPayment,
} from '@/components/plan/jami-payment-overlay';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Head, router } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarDays,
    Dumbbell,
    Salad,
    Smartphone,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';

type Props = {
    isPro: boolean;
    price: number;
    providers: {
        chapa: boolean;
        verify_checkout: boolean;
        jami: boolean;
    };
    pendingJamiPayment: PendingJamiPayment | null;
    checkoutUrl: string;
};

const BENEFITS = [
    {
        icon: Dumbbell,
        title: 'Every workout program',
        description:
            'All shared exercise programs, copied into your playlists.',
    },
    {
        icon: Salad,
        title: 'Every meal program',
        description: 'All shared meal programs, copied into your plans.',
    },
    {
        icon: CalendarDays,
        title: 'Your full 7-day plan',
        description: 'All days of your personalized meal plan, with swaps.',
    },
    {
        icon: Sparkles,
        title: 'Lifetime access',
        description: 'One payment — no renewals, no subscriptions to manage.',
    },
];

export default function Pro({
    isPro,
    price,
    providers,
    pendingJamiPayment,
    checkoutUrl,
}: Props) {
    const [gateway, setGateway] = useState('telebirr');
    const [phone, setPhone] = useState('');

    const pay = (
        provider: 'chapa' | 'verify_checkout' | 'jami',
        extra: { gateway?: string; customer_phone?: string } = {},
    ) => {
        router.post(checkoutUrl, { provider, ...extra });
    };

    return (
        <>
            <Head title="Go Pro" />
            {pendingJamiPayment !== null && (
                <JamiPaymentOverlay payment={pendingJamiPayment} />
            )}
            <MobileShell>
                <div className="flex flex-1 flex-col gap-4">
                    <Heading
                        title="TrainPro Pro"
                        description="One payment unlocks everything — forever."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                What you get
                            </CardTitle>
                            <CardDescription>
                                {price.toLocaleString()} ETB — one-time
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                {BENEFITS.map(
                                    ({ icon: Icon, title, description }) => (
                                        <li key={title} className="flex gap-3">
                                            <Icon className="mt-0.5 size-4 shrink-0 text-lime-600" />
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {description}
                                                </p>
                                            </div>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </CardContent>
                    </Card>

                    {isPro ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BadgeCheck className="size-4 text-green-600" />
                                    You're Pro
                                </CardTitle>
                                <CardDescription>
                                    Every program and meal plan is yours to
                                    claim.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Choose how to pay
                                </CardTitle>
                                <CardDescription>
                                    Secure checkout — Telebirr, M-Pesa and CBE
                                    Birr.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    {providers.jami && (
                                        <Button
                                            onClick={() => pay('jami')}
                                            className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                                        >
                                            Pay {price.toLocaleString()} ETB
                                            with Jami
                                        </Button>
                                    )}
                                    {providers.chapa && (
                                        <Button
                                            onClick={() => pay('chapa')}
                                            variant={
                                                providers.jami
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            className={
                                                providers.jami
                                                    ? 'w-full'
                                                    : 'w-full bg-lime-400 text-lime-950 hover:bg-lime-500'
                                            }
                                        >
                                            Pay {price.toLocaleString()} ETB
                                            with Chapa
                                        </Button>
                                    )}
                                    {providers.verify_checkout && (
                                        <Button
                                            onClick={() =>
                                                pay('verify_checkout')
                                            }
                                            variant="outline"
                                            className="w-full"
                                        >
                                            Pay {price.toLocaleString()} ETB
                                            with Verify
                                        </Button>
                                    )}
                                </div>

                                {providers.jami && (
                                    <>
                                        <div className="flex items-center gap-3">
                                            <Separator className="flex-1" />
                                            <span className="text-xs text-muted-foreground">
                                                or push to your phone
                                            </span>
                                            <Separator className="flex-1" />
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
                                                className="w-full"
                                                disabled={
                                                    phone.replace(/\D/g, '')
                                                        .length < 9
                                                }
                                                onClick={() =>
                                                    pay('jami', {
                                                        gateway,
                                                        customer_phone:
                                                            phone.replace(
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
                            </CardContent>
                        </Card>
                    )}
                </div>
            </MobileShell>
        </>
    );
}
