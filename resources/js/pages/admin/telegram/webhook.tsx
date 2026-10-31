import { Head, router, useForm } from '@inertiajs/react';
import { RefreshCw, Send, Trash2, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, update } from '@/routes/admin/telegram/webhook';

type WebhookInfo = {
    url: string;
    has_custom_certificate: boolean;
    pending_update_count: number;
    ip_address: string | null;
    last_error_date: number | null;
    last_error_message: string | null;
    last_synchronization_error_date: number | null;
    max_connections: number | null;
    allowed_updates: string[];
};

export default function AdminTelegramWebhook({
    configured,
    bot,
    info,
    expectedUrl,
    secretConfigured,
    apiError,
}: {
    configured: boolean;
    bot: { id: number | null; username: string | null; name: string | null } | null;
    info: WebhookInfo | null;
    expectedUrl: string;
    secretConfigured: boolean;
    apiError: string | null;
}) {
    const { confirm, confirmDialog } = useConfirm();
    const [dropPending, setDropPending] = useState(false);
    const form = useForm<{ url: string }>({ url: info?.url || expectedUrl });

    const status = !configured
        ? 'not-configured'
        : apiError !== null
          ? 'api-error'
          : info === null || info.url === ''
            ? 'not-registered'
            : info.url !== expectedUrl
              ? 'mismatch'
              : 'connected';

    const statusBadge = {
        'not-configured': <Badge variant="destructive">Bot not configured</Badge>,
        'api-error': <Badge variant="destructive">API unreachable</Badge>,
        'not-registered': <Badge variant="secondary">Not registered</Badge>,
        mismatch: <Badge variant="secondary">URL mismatch</Badge>,
        connected: <Badge>Connected</Badge>,
    }[status];

    function formatTimestamp(unix: number | null): string {
        return unix === null ? '—' : new Date(unix * 1000).toLocaleString();
    }

    function register(e: FormEvent) {
        e.preventDefault();
        form.put(update().url, { preserveScroll: true });
    }

    function remove() {
        confirm({
            title: 'Delete the webhook?',
            description:
                'Telegram will stop delivering updates — sign-in deep links, broadcast callbacks and /start handling all stop working.',
            confirmLabel: 'Delete webhook',
            destructive: true,
            onConfirm: () =>
                router.delete(destroy().url, {
                    data: { drop_pending_updates: dropPending },
                    preserveScroll: true,
                }),
        });
    }

    return (
        <>
            <Head title="Admin — Telegram webhook" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Telegram webhook"
                    description="How the bot receives updates from Telegram"
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.reload({ only: ['bot', 'info', 'apiError'] })}
                        >
                            <RefreshCw className="size-4" />
                            Refresh
                        </Button>
                    }
                />

                {apiError !== null && (
                    <div className="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            Telegram API error:{' '}
                            <span className="font-medium">{apiError}</span>
                        </p>
                    </div>
                )}

                {/* Status */}
                <Card>
                    <CardContent className="pt-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Status</h2>
                            {statusBadge}
                        </div>
                        <dl className="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-muted-foreground">Bot</dt>
                                <dd className="mt-0.5 font-medium">
                                    {bot ? (
                                        <>
                                            {bot.name}{' '}
                                            <span className="text-muted-foreground">
                                                @{bot.username}
                                            </span>
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Pending updates
                                </dt>
                                <dd className="mt-0.5 font-medium">
                                    {info?.pending_update_count ?? '—'}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs text-muted-foreground">
                                    Registered URL
                                </dt>
                                <dd className="mt-0.5 font-mono text-xs break-all">
                                    {info?.url !== '' ? info?.url : '—'}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs text-muted-foreground">
                                    Expected URL
                                </dt>
                                <dd className="mt-0.5 font-mono text-xs break-all">
                                    {expectedUrl}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Telegram IP
                                </dt>
                                <dd className="mt-0.5 font-mono text-xs">
                                    {info?.ip_address ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Max connections
                                </dt>
                                <dd className="mt-0.5 font-medium">
                                    {info?.max_connections ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Secret token
                                </dt>
                                <dd className="mt-0.5">
                                    <Badge variant="secondary">
                                        {secretConfigured
                                            ? 'Configured'
                                            : 'Not set'}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Allowed updates
                                </dt>
                                <dd className="mt-0.5 font-mono text-xs">
                                    {info && info.allowed_updates.length > 0
                                        ? info.allowed_updates.join(', ')
                                        : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Last sync error
                                </dt>
                                <dd className="mt-0.5 text-xs">
                                    {formatTimestamp(
                                        info?.last_synchronization_error_date ??
                                            null,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Last delivery error
                                </dt>
                                <dd className="mt-0.5 text-xs">
                                    {formatTimestamp(info?.last_error_date ?? null)}
                                </dd>
                            </div>
                        </dl>
                        {info?.last_error_message && (
                            <div className="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-600 dark:text-amber-400">
                                <span className="font-semibold">
                                    Last delivery error:
                                </span>{' '}
                                {info.last_error_message}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Set webhook */}
                <Card>
                    <CardContent className="pt-4">
                        <h2 className="text-sm font-semibold">Set webhook</h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Telegram requires HTTPS
                            {secretConfigured
                                ? ' — the configured secret token is attached automatically.'
                                : ' — no secret token is configured, so deliveries arrive unverified.'}
                        </p>
                        <form
                            onSubmit={register}
                            className="mt-4 flex items-start gap-2"
                        >
                            <div className="flex-1">
                                <Label htmlFor="webhook-url" className="sr-only">
                                    Webhook URL
                                </Label>
                                <Input
                                    id="webhook-url"
                                    value={form.data.url}
                                    onChange={(e) =>
                                        form.setData('url', e.target.value)
                                    }
                                    placeholder={expectedUrl}
                                    className="font-mono text-xs"
                                />
                                <InputError message={form.errors.url} />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing}
                            >
                                <Send className="size-4" />
                                {form.processing ? 'Setting…' : 'Set webhook'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Delete webhook */}
                <Card>
                    <CardContent className="pt-4">
                        <h2 className="text-sm font-semibold text-destructive">
                            Delete webhook
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Unregister the endpoint so Telegram stops sending
                            updates to this app.
                        </p>
                        <div className="mt-4 flex items-center justify-between gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={dropPending}
                                    onCheckedChange={(checked) =>
                                        setDropPending(checked === true)
                                    }
                                />
                                Drop pending updates
                            </label>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={remove}
                                disabled={info === null || info.url === ''}
                            >
                                <Trash2 className="size-4" />
                                Delete webhook
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            {confirmDialog}
        </>
    );
}
