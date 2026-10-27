import { Head, Link, router, usePage } from '@inertiajs/react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import { PaymentStatusBadge } from '@/components/admin/payment-status-badge';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index, refund, verify } from '@/routes/admin/payments';
import { show as showUser } from '@/routes/admin/users';
import type { AdminPayment } from '@/types';

type Props = {
    payment: AdminPayment;
};

const PROVIDER_LABELS: Record<string, string> = {
    chapa: 'Chapa',
    verify_checkout: 'Verify Checkout',
    jami: 'Jami',
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

export default function AdminPaymentShow({ payment }: Props) {
    const { errors } = usePage().props;
    const { confirm, confirmDialog } = useConfirm();

    function reverify() {
        router.post(verify.url(payment.id), {}, { preserveScroll: true });
    }

    function refundPayment() {
        confirm({
            title: `Refund ${payment.amount} ${payment.currency}?`,
            description:
                payment.provider === 'chapa'
                    ? `Refunds ${payment.tx_ref} via the Chapa API. This is best-effort — you may still need to complete it in the Chapa dashboard.`
                    : `${PROVIDER_LABELS[payment.provider] ?? payment.provider} payments are refunded manually from your provider dashboard or wallet — this only marks the payment refunded.`,
            confirmLabel: 'Refund',
            destructive: true,
            onConfirm: () =>
                router.post(
                    refund.url(payment.id),
                    {},
                    { preserveScroll: true },
                ),
        });
    }

    return (
        <>
            <Head title={`Admin — payment ${payment.tx_ref}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Payment detail"
                    description={payment.tx_ref}
                    actions={<PaymentStatusBadge status={payment.status} />}
                />

                {Object.keys(errors).length > 0 && (
                    <AlertError errors={Object.values(errors)} />
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {payment.amount} {payment.currency}
                                </p>
                                <dl className="mt-4 grid gap-3 text-sm">
                                    <Field label="Status">
                                        <PaymentStatusBadge
                                            status={payment.status}
                                        />
                                    </Field>
                                    <Field label="Created">
                                        {new Date(
                                            payment.created_at,
                                        ).toLocaleString()}
                                    </Field>
                                    <Field label="Paid at">
                                        {payment.paid_at
                                            ? new Date(
                                                  payment.paid_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </Field>
                                    <Field label="Refunded at">
                                        {payment.refunded_at
                                            ? new Date(
                                                  payment.refunded_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </Field>
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full justify-start"
                                    onClick={reverify}
                                    disabled={payment.status === 'success'}
                                >
                                    Re-verify with{' '}
                                    {PROVIDER_LABELS[payment.provider] ??
                                        payment.provider}
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    className="w-full justify-start"
                                    onClick={refundPayment}
                                    disabled={payment.status !== 'success'}
                                >
                                    Refund
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Payment
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <Field label="Payer">
                                        {[payment.first_name, payment.last_name]
                                            .filter(Boolean)
                                            .join(' ') || '—'}{' '}
                                        <span className="text-muted-foreground">
                                            ({payment.email})
                                        </span>
                                    </Field>
                                    <Field label="User">
                                        {payment.user ? (
                                            <Link
                                                href={showUser(payment.user.id)}
                                                className="text-primary underline-offset-4 hover:underline"
                                                prefetch
                                            >
                                                {payment.user.name} —{' '}
                                                {payment.user.email}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </Field>
                                    <Field label="Transaction reference">
                                        <span className="font-mono text-xs">
                                            {payment.tx_ref}
                                        </span>
                                    </Field>
                                    <Field label="Provider">
                                        {PROVIDER_LABELS[payment.provider] ??
                                            payment.provider}
                                    </Field>
                                    {payment.deposit_id !== null && (
                                        <Field label="Deposit id">
                                            <span className="font-mono text-xs">
                                                {payment.deposit_id}
                                            </span>
                                        </Field>
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Provider response
                                </CardTitle>
                                <CardDescription>
                                    Raw payload from the last API call
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {payment.provider_response === null ? (
                                    <p className="text-sm text-muted-foreground">
                                        No response stored.
                                    </p>
                                ) : (
                                    <pre className="max-h-96 overflow-auto rounded-md bg-muted p-4 text-xs">
                                        {JSON.stringify(
                                            payment.provider_response,
                                            null,
                                            2,
                                        )}
                                    </pre>
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

AdminPaymentShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Payments', href: index() },
    ],
};
