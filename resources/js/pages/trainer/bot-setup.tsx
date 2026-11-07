import { Form, Head, router } from '@inertiajs/react';
import { CheckCircle2, Copy, RefreshCw, Unplug, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/trainer';
import {
    destroy,
    reconnect,
    store,
    update,
} from '@/routes/trainer/bot';

type WebhookStatus = {
    status: 'ok' | 'mismatch' | 'missing' | 'unreachable';
    url?: string;
    expected_url?: string;
    pending_update_count?: number;
    last_error_date?: number | null;
    last_error_message?: string | null;
} | null;

type Props = {
    trainer: {
        brand_name: string | null;
        mini_app_name: string | null;
        bot_username: string | null;
        connected: boolean;
        invite_url: string;
        expected_webhook: string;
        webhook_registered: boolean;
        menu_button_set: boolean;
    };
    webhook: WebhookStatus;
};

const STATUS_META: Record<
    NonNullable<WebhookStatus>['status'],
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    ok: { label: 'Webhook live', variant: 'default' },
    missing: { label: 'No webhook set', variant: 'destructive' },
    mismatch: { label: 'Wrong URL', variant: 'destructive' },
    unreachable: { label: 'Cannot reach Telegram', variant: 'secondary' },
};

export default function BotSetup({ trainer, webhook }: Props) {
    const [, copy] = useClipboard();
    const status = webhook === null ? null : STATUS_META[webhook.status];

    return (
        <>
            <Head title="Bot setup" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Bot setup"
                    description="Your Telegram bot is the client-facing surface — connect it once and we handle the rest."
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Connect your bot
                            </CardTitle>
                            <CardDescription>
                                Create a bot with @BotFather, paste the token
                                here, and we'll register the webhook and the
                                Open App menu button automatically.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {trainer.connected ? (
                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div className="flex items-center gap-2 text-sm">
                                        <CheckCircle2 className="size-4 text-lime-600" />
                                        <span>
                                            Connected as{' '}
                                            <b>@{trainer.bot_username}</b>
                                        </span>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(destroy().url)
                                        }
                                    >
                                        <Unplug className="size-4" />
                                        Disconnect
                                    </Button>
                                </div>
                            ) : (
                                <Form {...store.form()} className="grid gap-3">
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="bot_token">
                                                    Bot token
                                                </Label>
                                                <Input
                                                    id="bot_token"
                                                    name="bot_token"
                                                    placeholder="123456:ABC-DEF…"
                                                    autoComplete="off"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.bot_token}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-fit"
                                            >
                                                {processing && <Spinner />}
                                                Connect bot
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}

                            <Form
                                {...update.form()}
                                className="grid gap-3 border-t pt-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="brand_name">
                                                Brand name{' '}
                                                <span className="text-muted-foreground">
                                                    (shown to clients)
                                                </span>
                                            </Label>
                                            <Input
                                                id="brand_name"
                                                name="brand_name"
                                                defaultValue={
                                                    trainer.brand_name ?? ''
                                                }
                                                placeholder="e.g. FitByMiki"
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="mini_app_name">
                                                Mini app short name{' '}
                                                <span className="text-muted-foreground">
                                                    (optional, from BotFather)
                                                </span>
                                            </Label>
                                            <Input
                                                id="mini_app_name"
                                                name="mini_app_name"
                                                defaultValue={
                                                    trainer.mini_app_name ?? ''
                                                }
                                                placeholder="myapp"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                            className="w-fit"
                                        >
                                            {processing && <Spinner />}
                                            Save
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle className="text-base">
                                        Webhook status
                                    </CardTitle>
                                    <CardDescription>
                                        Live check against the Bot API.
                                    </CardDescription>
                                </div>
                                {status && (
                                    <Badge variant={status.variant}>
                                        {status.label}
                                    </Badge>
                                )}
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                {webhook === null ? (
                                    <p className="text-muted-foreground">
                                        Connect a bot to see webhook status.
                                    </p>
                                ) : (
                                    <>
                                        <div className="grid gap-1">
                                            <span className="text-xs text-muted-foreground">
                                                Expected
                                            </span>
                                            <code className="truncate rounded bg-muted px-2 py-1 text-xs">
                                                {trainer.expected_webhook}
                                            </code>
                                        </div>
                                        <div className="grid gap-1">
                                            <span className="text-xs text-muted-foreground">
                                                Registered with Telegram
                                            </span>
                                            <code className="truncate rounded bg-muted px-2 py-1 text-xs">
                                                {webhook.url || '— none —'}
                                            </code>
                                        </div>
                                        <div className="flex gap-4 text-xs text-muted-foreground">
                                            <span>
                                                Pending updates:{' '}
                                                {webhook.pending_update_count ??
                                                    0}
                                            </span>
                                            <span>
                                                Menu button:{' '}
                                                {trainer.menu_button_set
                                                    ? 'set'
                                                    : 'not set'}
                                            </span>
                                        </div>
                                        {webhook.last_error_message && (
                                            <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-2 text-xs text-destructive">
                                                <XCircle className="mt-0.5 size-3.5 shrink-0" />
                                                <span>
                                                    {webhook.last_error_message}
                                                    {webhook.last_error_date &&
                                                        ` (${new Date(webhook.last_error_date * 1000).toLocaleString()})`}
                                                </span>
                                            </div>
                                        )}
                                        {webhook.status !== 'ok' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-fit"
                                                onClick={() =>
                                                    router.post(
                                                        reconnect().url,
                                                    )
                                                }
                                            >
                                                <RefreshCw className="size-4" />
                                                Re-register webhook
                                            </Button>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Client invite link
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-2">
                                <code className="flex-1 truncate rounded-md bg-muted px-3 py-2 text-xs">
                                    {trainer.invite_url}
                                </code>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={async () => {
                                        if (await copy(trainer.invite_url)) {
                                            toast.success('Link copied');
                                        }
                                    }}
                                >
                                    <Copy className="size-4" />
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

BotSetup.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Bot setup', href: '/trainer/bot' },
    ],
};
