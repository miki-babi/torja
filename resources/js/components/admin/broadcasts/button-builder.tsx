import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BroadcastButton } from '@/types';

const KIND_LABELS: Record<string, string> = {
    url: 'Web link',
    web_app: 'Mini App',
    callback: 'Callback (toast / track)',
    login_url: 'Login URL',
    switch_inline_query: 'Inline share (pick chat)',
    switch_inline_query_current_chat: 'Inline share (this chat)',
    switch_inline_query_chosen_chat: 'Inline share (filtered chats)',
    copy_text: 'Copy text',
    pay: 'Pay',
};

const KINDS_NEEDING_URL = ['url', 'web_app', 'login_url'];
const KINDS_NEEDING_QUERY = [
    'switch_inline_query',
    'switch_inline_query_current_chat',
    'switch_inline_query_chosen_chat',
];

function emptyButton(): BroadcastButton {
    return { text: '', kind: 'url', url: 'https://' };
}

/**
 * Inline keyboard grid editor — rows of up to 8 buttons covering every
 * Bot API button kind. Stored as `buttons` (array of rows) on the form.
 */
export function ButtonBuilder({
    rows,
    onChange,
    appUrl,
}: {
    rows: BroadcastButton[][];
    onChange: (rows: BroadcastButton[][]) => void;
    appUrl: string;
}) {
    function setButton(r: number, c: number, patch: Partial<BroadcastButton>) {
        const next = rows.map((row, i) =>
            i === r
                ? row.map((b, j) => (j === c ? { ...b, ...patch } : b))
                : row,
        );
        onChange(next);
    }

    function changeKind(r: number, c: number, kind: string) {
        const patch: Partial<BroadcastButton> = { kind };
        if (kind === 'web_app' || kind === 'login_url') {
            patch.url = appUrl;
        } else if (KINDS_NEEDING_URL.includes(kind)) {
            patch.url = 'https://';
        }
        if (kind === 'callback') {
            patch.action = patch.action ?? 'toast';
        }
        setButton(r, c, patch);
    }

    return (
        <div className="space-y-3">
            {rows.map((row, r) => (
                <div
                    key={r}
                    className="rounded-lg border border-input p-3 space-y-2"
                >
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground">
                            Row {r + 1} · {row.length}/8
                        </span>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={row.length >= 8}
                                onClick={() =>
                                    onChange(
                                        rows.map((x, i) =>
                                            i === r
                                                ? [...x, emptyButton()]
                                                : x,
                                        ),
                                    )
                                }
                            >
                                <Plus className="size-3" /> Button
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onChange(rows.filter((_, i) => i !== r))
                                }
                            >
                                <Trash2 className="size-3" />
                            </Button>
                        </div>
                    </div>

                    {row.map((button, c) => (
                        <div
                            key={c}
                            className="grid gap-2 rounded-md bg-muted/40 p-2 sm:grid-cols-[1fr_190px_auto] sm:items-end"
                        >
                            <div className="space-y-1">
                                <Label className="text-xs">Label</Label>
                                <Input
                                    value={button.text}
                                    maxLength={64}
                                    placeholder="Button text"
                                    onChange={(e) =>
                                        setButton(r, c, {
                                            text: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Type</Label>
                                <Select
                                    value={button.kind}
                                    onValueChange={(v) => changeKind(r, c, v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(KIND_LABELS).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                aria-label="Remove button"
                                onClick={() =>
                                    onChange(
                                        rows.map((x, i) =>
                                            i === r
                                                ? x.filter((_, j) => j !== c)
                                                : x,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-3" />
                            </Button>

                            <ButtonFields
                                button={button}
                                appUrl={appUrl}
                                onPatch={(patch) => setButton(r, c, patch)}
                            />
                        </div>
                    ))}
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...rows, [emptyButton()]])}
            >
                <Plus className="size-4" /> Add row
            </Button>
        </div>
    );
}

function ButtonFields({
    button,
    appUrl,
    onPatch,
}: {
    button: BroadcastButton;
    appUrl: string;
    onPatch: (patch: Partial<BroadcastButton>) => void;
}) {
    return (
        <div className="space-y-2 sm:col-span-3">
            {KINDS_NEEDING_URL.includes(button.kind) && (
                <div className="flex items-end gap-2">
                    <div className="flex-1 space-y-1">
                        <Label className="text-xs">URL</Label>
                        <Input
                            value={button.url ?? ''}
                            placeholder="https://…"
                            onChange={(e) =>
                                onPatch({ url: e.target.value })
                            }
                        />
                    </div>
                    {(button.kind === 'web_app' ||
                        button.kind === 'login_url') && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onPatch({ url: appUrl })}
                        >
                            Use this app
                        </Button>
                    )}
                </div>
            )}

            {button.kind === 'web_app' && (
                <p className="text-xs text-muted-foreground">
                    Mini App buttons require an https:// URL.
                </p>
            )}
            {button.kind === 'login_url' && (
                <p className="text-xs text-muted-foreground">
                    The domain must be registered with BotFather via
                    /setdomain.
                </p>
            )}

            {button.kind === 'login_url' && (
                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-56 space-y-1">
                        <Label className="text-xs">Button forward text</Label>
                        <Input
                            value={button.forward_text ?? ''}
                            maxLength={64}
                            placeholder="Log in"
                            onChange={(e) =>
                                onPatch({ forward_text: e.target.value })
                            }
                        />
                    </div>
                    <label className="flex items-center gap-2 text-xs">
                        <Checkbox
                            checked={button.request_write_access === true}
                            onCheckedChange={(v) =>
                                onPatch({ request_write_access: v === true })
                            }
                        />
                        Request write access
                    </label>
                </div>
            )}

            {button.kind === 'callback' && (
                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-40 space-y-1">
                        <Label className="text-xs">Action</Label>
                        <Select
                            value={button.action ?? 'toast'}
                            onValueChange={(v) => onPatch({ action: v })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="toast">
                                    Toast only
                                </SelectItem>
                                <SelectItem value="track">
                                    Track click + toast
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex-1 space-y-1">
                        <Label className="text-xs">Popup text</Label>
                        <Input
                            value={button.toast ?? ''}
                            maxLength={200}
                            placeholder="Thanks for your interest!"
                            onChange={(e) =>
                                onPatch({ toast: e.target.value })
                            }
                        />
                    </div>
                    <label className="flex items-center gap-2 text-xs">
                        <Checkbox
                            checked={button.show_alert === true}
                            onCheckedChange={(v) =>
                                onPatch({ show_alert: v === true })
                            }
                        />
                        Show as alert
                    </label>
                </div>
            )}

            {KINDS_NEEDING_QUERY.includes(button.kind) && (
                <div className="space-y-1">
                    <Label className="text-xs">Inline query</Label>
                    <Input
                        value={button.query ?? ''}
                        maxLength={256}
                        placeholder="Text prefilled when sharing"
                        onChange={(e) => onPatch({ query: e.target.value })}
                    />
                </div>
            )}

            {button.kind === 'switch_inline_query_chosen_chat' && (
                <div className="flex flex-wrap gap-3">
                    {(
                        [
                            ['allow_user_chats', 'Users'],
                            ['allow_bot_chats', 'Bots'],
                            ['allow_group_chats', 'Groups'],
                            ['allow_channel_chats', 'Channels'],
                        ] as const
                    ).map(([key, label]) => (
                        <label
                            key={key}
                            className="flex items-center gap-2 text-xs"
                        >
                            <Checkbox
                                checked={button[key] === true}
                                onCheckedChange={(v) =>
                                    onPatch({ [key]: v === true })
                                }
                            />
                            {label}
                        </label>
                    ))}
                </div>
            )}

            {button.kind === 'copy_text' && (
                <div className="space-y-1">
                    <Label className="text-xs">Text to copy</Label>
                    <Input
                        value={button.copy_text ?? ''}
                        maxLength={256}
                        placeholder="PROMO2026"
                        onChange={(e) =>
                            onPatch({ copy_text: e.target.value })
                        }
                    />
                </div>
            )}

            {button.kind === 'pay' && (
                <p className="text-xs text-muted-foreground">
                    Pay buttons only render inside invoice messages — they
                    are kept here for completeness.
                </p>
            )}
        </div>
    );
}
