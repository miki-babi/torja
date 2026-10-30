import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AudiencePicker } from '@/components/admin/broadcasts/audience-picker';
import { ButtonBuilder } from '@/components/admin/broadcasts/button-builder';
import { MediaPicker } from '@/components/admin/broadcasts/media-picker';
import { PollBuilder } from '@/components/admin/broadcasts/poll-builder';
import { TelegramPreview } from '@/components/admin/broadcasts/telegram-preview';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { TelegramEditor } from '@/components/telegram-editor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { store, test, update } from '@/routes/admin/broadcasts';
import type {
    AdminBroadcast,
    BroadcastAudienceConfig,
    BroadcastButton,
    BroadcastMediaItem,
    BroadcastPoll,
    BroadcastSegment,
    BroadcastUserHit,
} from '@/types';

const SINGLE_MEDIA = ['photo', 'video', 'document', 'animation'];
const SHOWS_BODY = (type: string): boolean =>
    type !== 'poll' && type !== 'media_group';

function defaultMedia(type: string): BroadcastMediaItem[] {
    if (SINGLE_MEDIA.includes(type)) {
        return [{ kind: type as BroadcastMediaItem['kind'], source: 'url', value: '', caption_html: '' }];
    }
    if (type === 'media_group') {
        return [
            { kind: 'photo', source: 'url', value: '', caption_html: '' },
            { kind: 'photo', source: 'url', value: '', caption_html: '' },
        ];
    }
    return [];
}

function defaultPoll(): BroadcastPoll {
    return {
        question: '',
        options: ['', ''],
        type: 'regular',
        is_anonymous: true,
        allows_multiple_answers: false,
        correct_option_id: null,
        explanation: '',
        open_period: null,
    };
}

function toDatetimeLocal(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso.replace(' ', 'T'));
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Approximate length of the converted Telegram text — the server does the
 * authoritative check; this gives the composer live feedback.
 */
function plainLen(html: string): number {
    return html
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'").length;
}

type BroadcastForm = {
    name: string;
    type: string;
    body_html: string;
    media: BroadcastMediaItem[];
    buttons: BroadcastButton[][];
    poll: BroadcastPoll | null;
    options: { disable_notification: boolean; protect_content: boolean };
    audience: BroadcastAudienceConfig;
    scheduled_at: string;
    recurrence: string;
    send_mode: 'draft' | 'now' | 'schedule';
};

type Props = {
    broadcast: AdminBroadcast | null;
    pickedUsers: BroadcastUserHit[];
    segments: BroadcastSegment[];
    linkedCount: number;
    types: string[];
    buttonKinds: string[];
    appUrl: string;
};

export default function BroadcastEdit({
    broadcast,
    pickedUsers: initialPicked,
    segments,
    linkedCount,
    types,
    appUrl,
}: Props) {
    const isNew = broadcast === null;

    const form = useForm<BroadcastForm>({
        name: broadcast?.name ?? '',
        type: broadcast?.type ?? 'text',
        body_html: broadcast?.body_html ?? '',
        media:
            broadcast?.media && broadcast.media.length > 0
                ? broadcast.media
                : defaultMedia(broadcast?.type ?? 'text'),
        buttons: broadcast?.buttons ?? [],
        poll: broadcast?.poll ?? defaultPoll(),
        options: broadcast?.options ?? {
            disable_notification: false,
            protect_content: false,
        },
        audience: broadcast?.audience_config ?? {
            everyone: false,
            segments: [],
            user_ids: [],
        },
        scheduled_at: toDatetimeLocal(broadcast?.scheduled_at),
        recurrence: broadcast?.recurrence ?? 'none',
        send_mode: 'draft',
    });

    const [picked, setPicked] = useState<BroadcastUserHit[]>(initialPicked);

    function changeType(type: string) {
        form.setData((data) => ({
            ...data,
            type,
            media:
                SINGLE_MEDIA.includes(type) || type === 'media_group'
                    ? data.media.length > 0 &&
                      (SINGLE_MEDIA.includes(data.type) ||
                          data.type === 'media_group')
                        ? data.media
                        : defaultMedia(type)
                    : [],
            poll: type === 'poll' ? (data.poll ?? defaultPoll()) : data.poll,
        }));
    }

    function setPickedUsers(users: BroadcastUserHit[]) {
        setPicked(users);
        form.setData('audience', {
            ...form.data.audience,
            user_ids: users.map((u) => u.id),
        });
    }

    /** First error under a field prefix (e.g. buttons.0.0.url). */
    function fieldError(prefix: string): string | undefined {
        return Object.entries(form.errors).find(
            ([key]) => key === prefix || key.startsWith(prefix + '.'),
        )?.[1];
    }

    function save(mode: BroadcastForm['send_mode']) {
        form.setData('send_mode', mode);
        if (isNew) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update.url(broadcast.id), { preserveScroll: true });
        }
    }

    const captionLimit =
        SINGLE_MEDIA.includes(form.data.type) || form.data.type === 'media_group'
            ? 1024
            : 4096;

    return (
        <>
            <Head
                title={`Admin — ${isNew ? 'New broadcast' : `Edit ${broadcast.name}`}`}
            />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={isNew ? 'New broadcast' : `Edit ${broadcast.name}`}
                    description="Compose a rich Telegram message — everything the app's keyboard can express."
                />

                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        save('draft');
                    }}
                    className="grid gap-4 lg:grid-cols-[1fr_320px]"
                >
                    <div className="max-w-2xl space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        placeholder="October challenge push"
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Message type</Label>
                                    <Select
                                        value={form.data.type}
                                        onValueChange={changeType}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {types.map((type) => (
                                                <SelectItem
                                                    key={type}
                                                    value={type}
                                                >
                                                    {type === 'media_group'
                                                        ? 'Album (media group)'
                                                        : type === 'animation'
                                                          ? 'GIF / animation'
                                                          : type[0].toUpperCase() +
                                                            type.slice(1)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.type} />
                                </div>
                            </CardContent>
                        </Card>

                        {SINGLE_MEDIA.includes(form.data.type) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Attachment
                                    </CardTitle>
                                    <CardDescription>
                                        Upload a file or paste a public URL —
                                        Telegram caches it after the first
                                        send.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <MediaPicker
                                        items={form.data.media}
                                        allowedKinds={[form.data.type]}
                                        onChange={(media) =>
                                            form.setData('media', media)
                                        }
                                    />
                                    <InputError message={fieldError('media')} />
                                </CardContent>
                            </Card>
                        )}

                        {form.data.type === 'media_group' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Album
                                    </CardTitle>
                                    <CardDescription>
                                        2–10 photos or videos, each with its
                                        own rich caption.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <MediaPicker
                                        album
                                        items={form.data.media}
                                        allowedKinds={['photo', 'video']}
                                        onChange={(media) =>
                                            form.setData('media', media)
                                        }
                                    />
                                    <InputError message={fieldError('media')} />
                                </CardContent>
                            </Card>
                        )}

                        {SHOWS_BODY(form.data.type) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {form.data.type === 'text'
                                            ? 'Message'
                                            : 'Caption'}
                                    </CardTitle>
                                    <CardDescription>
                                        Bold, italic, underline, strike,
                                        spoiler, code, quotes and links —
                                        converted to Telegram formatting.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <TelegramEditor
                                        value={form.data.body_html}
                                        onChange={(html) =>
                                            form.setData('body_html', html)
                                        }
                                    />
                                    <div className="mt-1 flex items-center justify-between">
                                        <InputError
                                            message={form.errors.body_html}
                                        />
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            ~{plainLen(form.data.body_html)}/
                                            {captionLimit}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {form.data.type === 'poll' && form.data.poll !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Poll
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PollBuilder
                                        poll={form.data.poll}
                                        onChange={(poll) =>
                                            form.setData('poll', poll)
                                        }
                                    />
                                    <InputError
                                        message={fieldError('poll')}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Inline buttons
                                </CardTitle>
                                <CardDescription>
                                    Rows of up to 8 — links, Mini App,
                                    callbacks, inline share, copy text and
                                    more.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ButtonBuilder
                                    rows={form.data.buttons}
                                    appUrl={appUrl}
                                    onChange={(buttons) =>
                                        form.setData('buttons', buttons)
                                    }
                                />
                                <InputError message={fieldError('buttons')} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Audience
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AudiencePicker
                                    value={form.data.audience}
                                    onChange={(audience) =>
                                        form.setData('audience', audience)
                                    }
                                    segments={segments}
                                    linkedCount={linkedCount}
                                    pickedUsers={picked}
                                    onPickUser={(user) =>
                                        setPickedUsers([...picked, user])
                                    }
                                    onRemoveUser={(id) =>
                                        setPickedUsers(
                                            picked.filter((u) => u.id !== id),
                                        )
                                    }
                                />
                                <InputError message={fieldError('audience')} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Delivery
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="scheduled_at">
                                            Send at
                                        </Label>
                                        <Input
                                            id="scheduled_at"
                                            type="datetime-local"
                                            value={form.data.scheduled_at}
                                            onChange={(e) =>
                                                form.setData(
                                                    'scheduled_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.scheduled_at}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Repeat</Label>
                                        <Select
                                            value={form.data.recurrence}
                                            onValueChange={(v) =>
                                                form.setData('recurrence', v)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    Does not repeat
                                                </SelectItem>
                                                <SelectItem value="daily">
                                                    Daily
                                                </SelectItem>
                                                <SelectItem value="weekly">
                                                    Weekly
                                                </SelectItem>
                                                <SelectItem value="monthly">
                                                    Monthly
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.recurrence}
                                        />
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                form.data.options
                                                    .disable_notification
                                            }
                                            onCheckedChange={(v) =>
                                                form.setData('options', {
                                                    ...form.data.options,
                                                    disable_notification:
                                                        v === true,
                                                })
                                            }
                                        />
                                        Send silently (no notification sound)
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                form.data.options
                                                    .protect_content
                                            }
                                            onCheckedChange={(v) =>
                                                form.setData('options', {
                                                    ...form.data.options,
                                                    protect_content:
                                                        v === true,
                                                })
                                            }
                                        />
                                        Protect content (block forwarding)
                                    </label>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                disabled={form.processing}
                                onClick={(e) => {
                                    e.preventDefault();
                                    save('draft');
                                }}
                            >
                                Save draft
                            </Button>
                            <Button
                                variant="secondary"
                                disabled={form.processing}
                                onClick={(e) => {
                                    e.preventDefault();
                                    save('now');
                                }}
                            >
                                Send now
                            </Button>
                            <Button
                                disabled={form.processing}
                                onClick={(e) => {
                                    e.preventDefault();
                                    save('schedule');
                                }}
                            >
                                {form.data.recurrence !== 'none'
                                    ? 'Schedule recurring'
                                    : 'Schedule send'}
                            </Button>
                            {!isNew && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        router.post(test.url(broadcast.id))
                                    }
                                >
                                    Send test to me
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="lg:sticky lg:top-4 lg:self-start">
                        <TelegramPreview
                            type={form.data.type}
                            bodyHtml={form.data.body_html}
                            media={form.data.media}
                            buttons={form.data.buttons}
                            poll={form.data.poll}
                        />
                    </div>
                </form>
            </div>
        </>
    );
}
