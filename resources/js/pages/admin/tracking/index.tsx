import { Head, router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Plus, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { destroy, index, store, update } from '@/routes/admin/tracking';

type Integration = {
    id: number;
    provider: string;
    name: string;
    external_id: string;
    scopes: string[];
    event_map: Record<string, string>;
    is_enabled: boolean;
    consent_required: boolean;
    has_secret: boolean;
};

type FormData = {
    provider: string;
    name: string;
    external_id: string;
    scopes: string[];
    event_map: Record<string, string>;
    is_enabled: boolean;
    consent_required: boolean;
    config: { access_token: string };
};

const PROVIDERS = ['meta', 'tiktok', 'google', 'custom'];
const EMPTY_FORM: FormData = {
    provider: 'meta',
    name: '',
    external_id: '',
    scopes: ['all'],
    event_map: {},
    is_enabled: false,
    consent_required: true,
    config: { access_token: '' },
};

export default function AdminTrackingIndex({
    integrations,
    scopeOptions,
    eventOptions,
}: {
    integrations: Integration[];
    scopeOptions: string[];
    eventOptions: string[];
}) {
    const { confirm, confirmDialog } = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Integration | null>(null);
    const form = useForm<FormData>({ ...EMPTY_FORM });
    const editForm = useForm<FormData>({ ...EMPTY_FORM });

    function create(e: FormEvent) {
        e.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setCreating(false);
            },
        });
    }

    function openEdit(integration: Integration) {
        setEditing(integration);
        editForm.setData({
            provider: integration.provider,
            name: integration.name,
            external_id: integration.external_id,
            scopes: integration.scopes,
            event_map: { ...integration.event_map },
            is_enabled: integration.is_enabled,
            consent_required: integration.consent_required,
            config: { access_token: '' },
        });
    }

    function saveEdit(e: FormEvent) {
        e.preventDefault();
        if (!editing) {
            return;
        }
        editForm.put(update(editing.id).url, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function toggleEnabled(integration: Integration) {
        router.put(
            update(integration.id).url,
            {
                provider: integration.provider,
                name: integration.name,
                external_id: integration.external_id,
                scopes: integration.scopes,
                event_map: integration.event_map,
                is_enabled: !integration.is_enabled,
                consent_required: integration.consent_required,
            },
            { preserveScroll: true },
        );
    }

    function remove(integration: Integration) {
        confirm({
            title: `Delete "${integration.name}"?`,
            description:
                'The provider script will stop being injected immediately.',
            confirmLabel: 'Delete',
            destructive: true,
            onConfirm: () =>
                router.delete(destroy(integration.id).url, {
                    preserveScroll: true,
                }),
        });
    }

    return (
        <>
            <Head title="Admin — tracking" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Tracking integrations"
                    description="Ad pixels and analytics scripts injected into the app"
                    actions={
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button size="sm">
                                    <Plus className="size-4" />
                                    New integration
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>New integration</DialogTitle>
                                </DialogHeader>
                                <IntegrationForm
                                    form={form}
                                    onSubmit={create}
                                    scopeOptions={scopeOptions}
                                    eventOptions={eventOptions}
                                    submitLabel="Add integration"
                                />
                            </DialogContent>
                        </Dialog>
                    }
                />

                <Card>
                    <CardContent className="p-0">
                        {integrations.length === 0 ? (
                            <EmptyState
                                title="No integrations yet"
                                description="Add a Meta pixel or TikTok pixel to start retargeting."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Integration</TableHead>
                                        <TableHead>Details</TableHead>
                                        <TableHead className="text-center">
                                            Enabled
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {integrations.map((integration) => (
                                        <TableRow key={integration.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {integration.name}
                                                </p>
                                                <Badge
                                                    variant="secondary"
                                                    className="mt-1"
                                                >
                                                    {integration.provider}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="max-w-72">
                                                <p className="truncate font-mono text-xs text-muted-foreground">
                                                    {integration.external_id}
                                                </p>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {integration.scopes.join(
                                                        ', ',
                                                    )}{' '}
                                                    ·{' '}
                                                    {
                                                        Object.keys(
                                                            integration.event_map,
                                                        ).length
                                                    }{' '}
                                                    mapped event
                                                    {Object.keys(
                                                        integration.event_map,
                                                    ).length === 1
                                                        ? ''
                                                        : 's'}
                                                    {integration.has_secret &&
                                                        ' · CAPI token set'}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Checkbox
                                                    checked={
                                                        integration.is_enabled
                                                    }
                                                    onCheckedChange={() =>
                                                        toggleEnabled(
                                                            integration,
                                                        )
                                                    }
                                                    aria-label={`Toggle ${integration.name}`}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Actions for ${integration.name}`}
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                openEdit(
                                                                    integration,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                remove(
                                                                    integration,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit integration</DialogTitle>
                    </DialogHeader>
                    <IntegrationForm
                        form={editForm}
                        onSubmit={saveEdit}
                        scopeOptions={scopeOptions}
                        eventOptions={eventOptions}
                        submitLabel="Save changes"
                        showSecretHint={editing?.has_secret}
                    />
                </DialogContent>
            </Dialog>
            {confirmDialog}
        </>
    );
}

function IntegrationForm({
    form,
    onSubmit,
    scopeOptions,
    eventOptions,
    submitLabel,
    showSecretHint,
}: {
    form: ReturnType<typeof useForm<FormData>>;
    onSubmit: (e: FormEvent) => void;
    scopeOptions: string[];
    eventOptions: string[];
    submitLabel: string;
    showSecretHint?: boolean;
}) {
    const mapEntries = Object.entries(form.data.event_map);

    function setScope(scope: string, on: boolean) {
        const scopes = on
            ? [...form.data.scopes, scope]
            : form.data.scopes.filter((s) => s !== scope);
        form.setData('scopes', scopes);
    }

    function setMapKey(index: number, key: string, value: string) {
        const entries = [...mapEntries];
        entries[index] = [key, value];
        form.setData('event_map', Object.fromEntries(entries));
    }

    function addMapRow() {
        const key = eventOptions.find((e) => !(e in form.data.event_map));

        if (key) {
            setMapKey(mapEntries.length, key, '');
        }
    }

    function removeMapRow(index: number) {
        const entries = mapEntries.filter((_, i) => i !== index);
        form.setData('event_map', Object.fromEntries(entries));
    }

    return (
        <form onSubmit={onSubmit} className="grid gap-4">
            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="provider">Provider</Label>
                    <Select
                        value={form.data.provider}
                        onValueChange={(v) => form.setData('provider', v)}
                    >
                        <SelectTrigger id="provider">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PROVIDERS.map((p) => (
                                <SelectItem key={p} value={p}>
                                    {p}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Main Meta pixel"
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="external_id">Pixel / tracking ID</Label>
                <Input
                    id="external_id"
                    value={form.data.external_id}
                    onChange={(e) =>
                        form.setData('external_id', e.target.value)
                    }
                    placeholder="1234567890"
                    required
                />
                <InputError message={form.errors.external_id} />
            </div>

            <div className="grid gap-1.5">
                <Label>Page scopes</Label>
                <div className="flex flex-wrap gap-3">
                    {['all', ...scopeOptions].map((scope) => (
                        <label
                            key={scope}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data.scopes.includes(scope)}
                                onCheckedChange={(checked) =>
                                    setScope(scope, checked === true)
                                }
                            />
                            {scope}
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.scopes} />
            </div>

            <div className="grid gap-2">
                <Label>Event map</Label>
                <p className="text-xs text-muted-foreground">
                    Internal event → provider event (e.g. payment.succeeded →
                    Purchase). Unmapped events stay first-party only.
                </p>
                {mapEntries.map(([key, value], index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Select
                            value={key}
                            onValueChange={(v) => {
                                const entries = [...mapEntries];
                                entries[index] = [v, value];
                                form.setData(
                                    'event_map',
                                    Object.fromEntries(entries),
                                );
                            }}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {eventOptions.map((event) => (
                                    <SelectItem key={event} value={event}>
                                        {event}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <span className="text-muted-foreground">→</span>
                        <Input
                            value={value}
                            onChange={(e) =>
                                setMapKey(index, key, e.target.value)
                            }
                            placeholder="Purchase"
                            className="w-48"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            onClick={() => removeMapRow(index)}
                            aria-label="Remove mapping"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addMapRow}
                    >
                        <Plus className="size-4" />
                        Add mapping
                    </Button>
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="access_token">
                    CAPI access token (optional)
                </Label>
                <Input
                    id="access_token"
                    type="password"
                    value={form.data.config.access_token}
                    onChange={(e) =>
                        form.setData('config', {
                            access_token: e.target.value,
                        })
                    }
                    placeholder={
                        showSecretHint
                            ? 'Leave blank to keep the current token'
                            : 'For server-side Conversions API'
                    }
                    autoComplete="off"
                />
            </div>

            <div className="flex gap-4">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.is_enabled}
                        onCheckedChange={(checked) =>
                            form.setData('is_enabled', checked === true)
                        }
                    />
                    Enabled
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.consent_required}
                        onCheckedChange={(checked) =>
                            form.setData('consent_required', checked === true)
                        }
                    />
                    Require consent
                </label>
            </div>

            <Button type="submit" className="w-fit" disabled={form.processing}>
                {submitLabel}
            </Button>
        </form>
    );
}

AdminTrackingIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Tracking', href: index() },
    ],
};
