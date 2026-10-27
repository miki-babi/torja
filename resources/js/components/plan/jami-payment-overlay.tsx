import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cancel, status, verify } from '@/routes/payment';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export type PendingJamiPayment = {
    id: number;
    amount: number;
    currency: string;
    gateway: string | null;
    phone: string | null;
    checkoutUrl: string | null;
};

const GATEWAY_LABELS: Record<string, string> = {
    telebirr: 'Telebirr',
    mpesa: 'M-Pesa',
    cbe: 'CBE Birr',
};

/**
 * Full-screen, non-dismissable overlay shown while a Jami payment is
 * pending — after a direct push to the buyer's phone or when they come
 * back with an unfinished payment link. Polls the JSON status endpoint
 * quietly and does one real verify visit when the payment resolves.
 */
export function JamiPaymentOverlay({
    payment,
}: {
    payment: PendingJamiPayment;
}) {
    const [timedOut, setTimedOut] = useState(false);

    const checkStatus = async () => {
        try {
            const response = await fetch(status.url(payment.id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return;
            }

            const { status: paymentStatus } = await response.json();

            if (paymentStatus !== 'pending') {
                router.post(verify.url(payment.id));
            }
        } catch {
            // Transient network error — keep polling until the cap.
        }
    };

    // Poll until the payment resolves or ~2 minutes pass, then offer a
    // manual check.
    useEffect(() => {
        setTimedOut(false);

        let attempts = 0;
        const timer = setInterval(() => {
            attempts += 1;

            if (attempts > 30) {
                clearInterval(timer);
                setTimedOut(true);
                return;
            }

            void checkStatus();
        }, 4000);

        return () => clearInterval(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [payment.id]);

    const gatewayLabel = payment.gateway
        ? (GATEWAY_LABELS[payment.gateway] ?? payment.gateway)
        : null;
    const isDirectPush = gatewayLabel !== null && payment.phone !== null;

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-8 bg-background px-6 text-center"
        >
            <div className="flex flex-col items-center gap-3">
                <Spinner className="size-10 text-lime-600" />
                <h1 className="text-xl font-semibold">
                    Waiting for confirmation
                </h1>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {isDirectPush
                        ? timedOut
                            ? `It's taking longer than expected — the ${gatewayLabel} prompt to ${payment.phone} may have expired.`
                            : `Approve the ${gatewayLabel} payment prompt sent to ${payment.phone}.`
                        : timedOut
                          ? "It's taking longer than expected — the payment link may have expired."
                          : 'Finish paying on the secure Jami page — this screen updates automatically.'}
                </p>
                <p className="text-lg font-medium tabular-nums">
                    {payment.amount.toLocaleString()} {payment.currency}
                </p>
            </div>

            <div className="flex w-full max-w-xs flex-col gap-2">
                {timedOut && (
                    <Button
                        onClick={() => void checkStatus()}
                        className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                    >
                        Check status
                    </Button>
                )}
                {payment.checkoutUrl !== null && (
                    <Button
                        onClick={() => {
                            window.location.href = payment.checkoutUrl!;
                        }}
                        variant="outline"
                        className="w-full"
                    >
                        Resume payment
                    </Button>
                )}
                <Button
                    variant="outline"
                    className="w-full"
                    onClick={() => router.post(cancel.url(payment.id))}
                >
                    Cancel and try again
                </Button>
            </div>
        </div>
    );
}
